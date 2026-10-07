<?php

namespace App\Services;

use App\Models\AppConfig;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ObrLog;
use App\Models\Product;
use App\Models\Scopes\CompanyScope;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReviewInvoice
{
    public const MOTIF_REMPLACEMENT_TVA = 'Facture envoyée sans TVA, remplacée par une facture avec TVA';

    /**
     * Factures normales acceptées par l'OBR sans TVA (ou celles des numéros donnés, même si la TVA a été corrigée en local),
     * plus celles déjà annulées avec ce motif mais pas encore recréées.
     *
     * @param  array<int, string>  $numeros
     */
    public static function facturesARemplacer(array $numeros = [], string $motif = self::MOTIF_REMPLACEMENT_TVA): Builder
    {
        $dejaRemplacees = Invoice::query()->whereNotNull('old_invoice_reference')->select('old_invoice_reference');
        $dejaAnnulee = fn (Builder $q) => $q->where('is_cancelled', true)->where('cancel_reason', $motif);

        return Invoice::query()
            ->where('invoice_type', 'FN')
            ->whereNull('old_invoice_reference')
            ->whereNotIn('id', $dejaRemplacees)
            ->when(
                $numeros,
                fn (Builder $q) => $q->whereIn('invoice_number', $numeros)->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $q->where('is_cancelled', false)->where('obr_submission_status', 'ACCEPTED'))
                    ->orWhere($dejaAnnulee)),
                fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $q->where('is_cancelled', false)
                        ->where('obr_submission_status', 'ACCEPTED')
                        ->where('invoice_vat_amount', 0))
                    ->orWhere($dejaAnnulee))
            )
            ->orderBy('invoice_date');
    }

    public static function review($invoiceID)
    {
        $invoice = Invoice::find($invoiceID);
        // check Electronique Signature
        $obr = new ObrService;
        $signature = $obr->generateInvoiceIdentifier($invoice->invoice_number, $invoice->invoice_date);
        $invoice->electronic_signature = $signature;
        $invoice->tp_TIN = AppConfig::getConfigKey('OBR_NIF');
        $invoice->save();

        return $invoice;
    }

    /**
     * Extrait une TVA de 18% des prix TTC déjà saisis sur la facture.
     * Le total TTC reste inchangé (ex: 2500 TTC => 2118.64 HTVA + 381.36 TVA).
     * Seuls les articles sans TVA (vat = 0) sont traités, pour éviter de l'appliquer deux fois.
     * item_price devient le prix unitaire HTVA et vat le taux : ObrService recalcule la TVA à partir de ces deux champs.
     */
    public static function calculTVA(Invoice|int $invoice, float $tauxTVA = 18): Invoice
    {
        if (! $invoice instanceof Invoice) {
            $invoice = Invoice::findOrFail($invoice);
        }
        $invoice->loadMissing('invoiceItems');
        $coef = 1 + $tauxTVA / 100;

        DB::transaction(function () use ($invoice, $tauxTVA, $coef) {
            $totalHTVA = 0;
            $totalTVA = 0;

            foreach ($invoice->invoiceItems as $item) {
                if ((float) $item->vat == 0 && (float) $item->item_quantity > 0) {
                    $quantity = (float) $item->item_quantity;
                    $totalTTC = round((float) $item->item_price * $quantity, 2);
                    $prixHTVA = round($item->item_price / $coef, 2);

                    $item->item_price = $prixHTVA;
                    $item->vat = $tauxTVA;
                    $item->item_price_nvat = $prixHTVA;
                    $item->item_price_wvat = round($totalTTC / $quantity, 2);
                    $item->item_total_amount = $totalTTC;
                    $item->save();
                }

                $ligneHTVA = round($item->item_total_amount / (1 + $item->vat / 100), 2);
                $totalHTVA += $ligneHTVA;
                $totalTVA += $item->item_total_amount - $ligneHTVA;
            }

            $invoice->invoice_amount_nvat = round($totalHTVA, 2);
            $invoice->invoice_vat_amount = round($totalTVA, 2);
            $invoice->invoice_total_amount = round($totalHTVA + $totalTVA, 2);
            $invoice->save();
        });

        return $invoice->fresh('invoiceItems');
    }

    /**
     * Annule une facture : d'abord chez l'OBR si elle y a été envoyée, puis en local
     * (retour du stock pour les ventes POS + marquage de la facture comme annulée).
     * Si l'OBR refuse l'annulation, rien n'est modifié en local.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public static function annulerFacture(Invoice|int $invoice, string $motif, bool $restaurerStock = true): array
    {
        if (! $invoice instanceof Invoice) {
            $invoice = Invoice::findOrFail($invoice);
        }
        $invoice->loadMissing('invoiceItems');

        if ($invoice->is_cancelled) {
            return ['success' => false, 'message' => 'Cette facture est déjà annulée'];
        }

        $invoiceIdentifier = $invoice->obr_invoice_identifier ?: $invoice->electronic_signature;
        $requireObrCancel = in_array($invoice->obr_submission_status, ['ACCEPTED', 'SENT'], true)
            || ! empty($invoice->obr_invoice_identifier);
        $obrCancelResult = null;

        if ($requireObrCancel) {
            if (! $invoiceIdentifier) {
                return ['success' => false, 'message' => 'Identifiant OBR introuvable pour annuler cette facture.'];
            }

            $obrCancelResult = (new ObrService)->cancelInvoice($invoiceIdentifier, $motif);
            ObrLog::logInvoiceCancelled($invoice, $obrCancelResult, $motif);

            if (! $obrCancelResult['success']) {
                return [
                    'success' => false,
                    'message' => $obrCancelResult['message'] ?? 'Annulation refusée par OBR.',
                    'data' => ['obr_result' => $obrCancelResult],
                ];
            }
        }

        $stockRestored = DB::transaction(function () use ($invoice, $motif, $restaurerStock) {
            $stockRestored = [];

            if ($restaurerStock && $invoice->invoice_identifier === 'POS') {
                $stockRestored = self::restaurerStock($invoice, $motif);
            }

            $invoice->update([
                'is_cancelled' => true,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $motif,
            ]);

            return $stockRestored;
        });

        return [
            'success' => true,
            'message' => 'Facture annulée avec succès',
            'data' => [
                'invoice' => $invoice->fresh(),
                'stock_restored' => $stockRestored,
                'obr_cancelled' => $requireObrCancel,
                'obr_result' => $obrCancelResult,
            ],
        ];
    }

    /**
     * Remplace une facture envoyée à l'OBR sans TVA : annulation chez l'OBR (sans retour de stock,
     * la marchandise ayant bien été vendue), puis création d'une copie avec un nouveau numéro, la même date,
     * old_invoice_reference = id de la facture annulée et la TVA extraite du prix TTC.
     * La nouvelle facture est mise en PENDING : app:obr-sync-command l'enverra à l'OBR.
     * Peut être relancée sans risque : une facture déjà annulée mais pas encore remplacée est simplement recréée.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public static function remplacerFacture(Invoice|int $invoice, string $motif, float $tauxTVA = 18): array
    {
        if (! $invoice instanceof Invoice) {
            $invoice = Invoice::findOrFail($invoice);
        }

        $remplacementExistant = Invoice::where('old_invoice_reference', $invoice->id)->first();
        if ($remplacementExistant) {
            return ['success' => false, 'message' => "Déjà remplacée par la facture {$remplacementExistant->invoice_number}"];
        }

        if (! $invoice->is_cancelled) {
            $annulation = self::annulerFacture($invoice, $motif, false);
            if (! $annulation['success']) {
                return $annulation;
            }
        }

        $nouvelleFacture = DB::transaction(function () use ($invoice, $tauxTVA) {
            $invoice->loadMissing('invoiceItems');

            $nouvelleFacture = $invoice->replicate([
                'electronic_signature',
                'invoice_registered_number',
                'invoice_registered_date',
                'obr_response_message',
                'obr_invoice_identifier',
                'obr_invoice_registered_number',
                'obr_invoice_registered_date',
                'obr_electronic_signature',
                'obr_sent_at',
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
                'deleted_at',
            ]);
            $nouvelleFacture->invoice_number = 'TEMP';
            $nouvelleFacture->invoice_date = $invoice->invoice_date;
            $nouvelleFacture->is_cancelled = false;
            $nouvelleFacture->obr_submission_status = 'PENDING';
            $nouvelleFacture->old_invoice_reference = $invoice->id;
            $nouvelleFacture->save();

            $nouvelleFacture->invoice_number = Invoice::getInvoiceNumber($nouvelleFacture->id);
            $nouvelleFacture->electronic_signature = (new ObrService)->generateInvoiceIdentifier(
                $nouvelleFacture->invoice_number,
                $nouvelleFacture->invoice_date
            );
            $nouvelleFacture->save();

            foreach ($invoice->invoiceItems as $item) {
                $nouvelItem = $item->replicate(['deleted_at']);
                $nouvelItem->invoice_id = $nouvelleFacture->id;
                $nouvelItem->save();
            }

            return self::calculTVA($nouvelleFacture, $tauxTVA);
        });

        return [
            'success' => true,
            'message' => "Facture {$invoice->invoice_number} remplacée par {$nouvelleFacture->invoice_number}",
            'data' => ['ancienne' => $invoice->fresh(), 'nouvelle' => $nouvelleFacture],
        ];
    }

    /**
     * Copies créées par remplacerFacture pour les factures annulées avec ce motif, encore jamais envoyées à l'OBR (PENDING).
     * Une copie est reconnue par old_invoice_reference, ou à défaut (copies créées avant ce champ) par
     * la même entreprise, le même client, la même date et le même total TTC qu'une seule facture annulée, avec un id plus grand.
     *
     * @return Collection<int, Invoice> copies, avec l'attribut facture_remplacee = numéro de la facture d'origine
     */
    public static function copiesDeRemplacement(string $motif = self::MOTIF_REMPLACEMENT_TVA): Collection
    {
        $originaux = Invoice::query()->where('is_cancelled', true)->where('cancel_reason', $motif)->get();
        if ($originaux->isEmpty()) {
            return new Collection;
        }

        $cle = fn (Invoice $facture) => implode('|', [
            $facture->company_id,
            $facture->customer_id,
            $facture->invoice_date?->format('Y-m-d H:i:s'),
            $facture->invoice_total_amount,
        ]);
        $candidats = Invoice::query()
            ->where('invoice_type', 'FN')
            ->where('is_cancelled', false)
            ->where('obr_submission_status', 'PENDING')
            ->where('id', '>', $originaux->min('id'))
            ->get();
        $candidatsParCle = $candidats->groupBy($cle);

        $copies = new Collection;
        foreach ($originaux as $original) {
            $copie = $candidats->firstWhere('old_invoice_reference', (string) $original->id);
            if (! $copie) {
                $memes = $candidatsParCle->get($cle($original), collect())
                    ->filter(fn (Invoice $c) => $c->id > $original->id && $c->old_invoice_reference === null);
                $copie = $memes->count() === 1 ? $memes->first() : null;
            }
            if ($copie && ! $copies->contains('id', $copie->id)) {
                $copie->setAttribute('facture_remplacee', $original->invoice_number);
                $copies->push($copie);
            }
        }

        return $copies;
    }

    /**
     * Supprime (soft delete) les copies de remplacement et leurs lignes, pour pouvoir recréer les factures d'origine.
     *
     * @param  Collection<int, Invoice>  $copies
     */
    public static function supprimerCopiesDeRemplacement(Collection $copies): int
    {
        $ids = $copies->pluck('id');

        return DB::transaction(function () use ($ids) {
            InvoiceItem::whereIn('invoice_id', $ids)->delete();

            return Invoice::whereIn('id', $ids)->where('obr_submission_status', 'PENDING')->delete();
        });
    }

    /**
     * Applique la TVA aux produits d'une entreprise qui n'en ont pas (vat_rate = 0) en gardant le même prix TTC :
     * le prix actuel est conservé dans price_ttc / price_tvac, et price devient le prix HTVA (prix / 1.18).
     * Les prix d'entrepôt (unit_price) et les prix promo sont aussi convertis, car le POS ajoute la TVA par-dessus.
     *
     * @return array{produits: int, prix_entrepots: int}
     */
    public static function appliquerTVAProduits(int $companyId, float $tauxTVA = 18): array
    {
        $coef = 1 + $tauxTVA / 100;
        $resultat = ['produits' => 0, 'prix_entrepots' => 0];

        DB::transaction(function () use ($companyId, $tauxTVA, $coef, &$resultat) {
            Product::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where(fn ($query) => $query->whereNull('vat_rate')->orWhere('vat_rate', 0))
                ->chunkById(200, function ($produits) use ($tauxTVA, $coef, &$resultat) {
                    foreach ($produits as $produit) {
                        $prixTTC = (float) $produit->price;
                        $produit->price_ttc = $prixTTC;
                        $produit->price_tvac = $prixTTC;
                        $produit->price = round($prixTTC / $coef, 2);
                        if ((float) $produit->price_promo > 0) {
                            $produit->price_promo = round($produit->price_promo / $coef, 2);
                        }
                        $produit->vat_rate = $tauxTVA;
                        $produit->save();
                        $resultat['produits']++;

                        $prixEntrepots = WarehouseProduct::withoutGlobalScope(CompanyScope::class)
                            ->where('product_id', $produit->id)
                            ->get();

                        foreach ($prixEntrepots as $prixEntrepot) {
                            if ((float) $prixEntrepot->unit_price > 0) {
                                $prixEntrepot->unit_price = round($prixEntrepot->unit_price / $coef, 2);
                            }
                            if ((float) $prixEntrepot->price_promo > 0) {
                                $prixEntrepot->price_promo = round($prixEntrepot->price_promo / $coef, 2);
                            }
                            $prixEntrepot->save();
                            $resultat['prix_entrepots']++;
                        }
                    }
                });
        });

        return $resultat;
    }

    /**
     * Crée un mouvement de retour (ER = Entrée Retour) pour chaque article vendu et remet la quantité en entrepôt.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function restaurerStock(Invoice $invoice, string $motif): array
    {
        $stockRestored = [];

        foreach ($invoice->invoiceItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $originalMovement = StockMovement::where('item_movement_invoice_ref', $invoice->invoice_number)
                ->where('product_id', $item->product_id)
                ->whereIn('item_movement_type', ['VENTE', 'SN'])
                ->first();

            if (! $originalMovement) {
                continue;
            }

            $returnMovement = StockMovement::create([
                'system_or_device_id' => $originalMovement->system_or_device_id,
                'product_id' => $item->product_id,
                'warehouse_id' => $originalMovement->warehouse_id,
                'item_code' => $originalMovement->item_code,
                'item_designation' => $originalMovement->item_designation,
                'item_quantity' => $item->item_quantity,
                'item_measurement_unit' => $originalMovement->item_measurement_unit,
                'item_purchase_or_sale_price' => $item->item_price,
                'item_purchase_or_sale_currency' => $invoice->invoice_currency ?? 'BIF',
                'item_movement_type' => 'ER',
                'item_movement_invoice_ref' => $invoice->invoice_number,
                'item_movement_description' => "Retour après annulation - {$motif}",
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => $invoice->company_id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

            WarehouseProduct::where('warehouse_id', $originalMovement->warehouse_id)
                ->where('product_id', $item->product_id)
                ->first()
                ?->increment('quantity', $item->item_quantity);

            $stockRestored[] = [
                'product_id' => $item->product_id,
                'product_name' => $originalMovement->item_designation,
                'quantity_restored' => $item->item_quantity,
                'warehouse_id' => $originalMovement->warehouse_id,
                'movement_id' => $returnMovement->id,
            ];
        }

        return $stockRestored;
    }
}
