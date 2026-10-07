<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MouvementStockImportation;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Envoi à l'OBR de tout ce qui est en attente (utilisé par app:obr-sync-command et le bouton « Synchroniser OBR »).
 */
class ObrSynchronisation
{
    public function __construct(private ObrService $obrService) {}

    /**
     * Envoie les éléments en attente ; avec $limite, au plus $limite éléments de chaque type (envoi par lots depuis l'interface).
     *
     * @return array{stocks: array, factures: array, importations: array, details: array<int, array<string, mixed>>, restants: int}
     */
    public function toutSynchroniser(?int $limite = null): array
    {
        $resultats = [
            'stocks' => $this->synchroniserStocks($limite),
            'factures' => $this->synchroniserFactures($limite),
            'importations' => $this->synchroniserImportations($limite),
        ];

        return $resultats + [
            'details' => array_merge(...array_column($resultats, 'details')),
            'restants' => $this->restants(),
        ];
    }

    /**
     * Nombre d'éléments encore en attente d'envoi.
     */
    public function restants(): int
    {
        return $this->stocksEnAttente()->count()
            + $this->facturesEnAttente()->count()
            + $this->importationsEnAttente()->count();
    }

    /**
     * @return array{total: int, success: int, failed: int, errors: array, details: array}
     */
    public function synchroniserStocks(?int $limite = null): array
    {
        return $this->envoyer(
            'Stock',
            $this->stocksEnAttente()->when($limite, fn (Builder $q) => $q->limit($limite))->get(),
            fn (StockMovement $mouvement) => $this->obrService->addStockMovement($mouvement),
            fn (StockMovement $mouvement) => trim("{$mouvement->item_movement_type} {$mouvement->item_designation}")
        );
    }

    /**
     * @return array{total: int, success: int, failed: int, errors: array, details: array}
     */
    public function synchroniserFactures(?int $limite = null): array
    {
        return $this->envoyer(
            'Facture',
            $this->facturesEnAttente()->with(['company', 'invoiceItems'])->when($limite, fn (Builder $q) => $q->limit($limite))->get(),
            fn (Invoice $facture) => $this->obrService->addInvoice(ReviewInvoice::review($facture->id)),
            fn (Invoice $facture) => $facture->invoice_number
        );
    }

    /**
     * @return array{total: int, success: int, failed: int, errors: array, details: array}
     */
    public function synchroniserImportations(?int $limite = null): array
    {
        return $this->envoyer(
            'Importation',
            $this->importationsEnAttente()->when($limite, fn (Builder $q) => $q->limit($limite))->get(),
            fn (MouvementStockImportation $mouvement) => $this->obrService->addStockMovementImportation($mouvement),
            fn (MouvementStockImportation $mouvement) => (string) ($mouvement->item_designation ?? $mouvement->id)
        );
    }

    private function stocksEnAttente(): Builder
    {
        return StockMovement::where('obr_submission_status', 'PENDING')->latest();
    }

    private function facturesEnAttente(): Builder
    {
        return Invoice::where('obr_submission_status', 'PENDING')->latest();
    }

    private function importationsEnAttente(): Builder
    {
        return MouvementStockImportation::where('is_sent_to_obr', 0);
    }

    /**
     * Envoie chaque élément et garde la réponse de l'OBR (message) pour l'afficher.
     *
     * @param  iterable<int, mixed>  $elements
     * @return array{total: int, success: int, failed: int, errors: array, details: array}
     */
    private function envoyer(string $type, iterable $elements, callable $envoi, callable $reference): array
    {
        $resultats = ['total' => 0, 'success' => 0, 'failed' => 0, 'errors' => [], 'details' => []];

        foreach ($elements as $element) {
            try {
                $resultat = $envoi($element);
                $succes = is_array($resultat) && ($resultat['success'] ?? false);
                $message = is_array($resultat) ? ($resultat['message'] ?? '') : 'Réponse OBR vide';
            } catch (Throwable $e) {
                report($e);
                $succes = false;
                $message = 'Erreur de connexion à l\'OBR : '.$e->getMessage();
            }

            $detail = [
                'type' => $type,
                'id' => $element->id,
                'reference' => $reference($element),
                'success' => $succes,
                'message' => $message,
            ];

            $resultats['total']++;
            $resultats['details'][] = $detail;
            if ($succes) {
                $resultats['success']++;
            } else {
                $resultats['failed']++;
                $resultats['errors'][] = $detail;
            }
        }

        return $resultats;
    }
}
