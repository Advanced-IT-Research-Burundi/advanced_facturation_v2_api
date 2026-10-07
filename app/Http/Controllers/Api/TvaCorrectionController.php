<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\ObrService;
use App\Services\ReviewInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Correction des factures envoyées à l'OBR sans TVA (onglet « Correction TVA » des paramètres).
 */
class TvaCorrectionController extends Controller
{
    /**
     * Liste des factures à remplacer et nombre de produits encore sans TVA.
     */
    public function index(Request $request): JsonResponse
    {
        $numeros = array_filter(array_map('trim', explode(',', (string) $request->input('numeros', ''))));

        $factures = ReviewInvoice::facturesARemplacer($numeros)
            ->with('customer:id,customer_name')
            ->get(['id', 'invoice_number', 'invoice_date', 'invoice_total_amount', 'invoice_vat_amount', 'is_cancelled', 'customer_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'factures' => $factures,
                'total_ttc' => round((float) $factures->sum('invoice_total_amount'), 2),
                'produits_sans_tva' => Product::query()
                    ->where(fn ($q) => $q->whereNull('vat_rate')->orWhere('vat_rate', 0))
                    ->count(),
            ],
        ]);
    }

    /**
     * Annule la facture chez l'OBR, la recrée avec la TVA (même date) et envoie la nouvelle facture à l'OBR.
     */
    public function replace(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'taux' => 'nullable|numeric|min:0|max:100',
        ]);

        if (! ReviewInvoice::facturesARemplacer([$invoice->invoice_number])->whereKey($invoice->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "La facture {$invoice->invoice_number} n'est pas à remplacer (non acceptée par l'OBR, ou déjà remplacée).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resultat = ReviewInvoice::remplacerFacture(
            $invoice,
            ReviewInvoice::MOTIF_REMPLACEMENT_TVA,
            (float) ($validated['taux'] ?? 18)
        );

        if (! $resultat['success']) {
            return response()->json($resultat, Response::HTTP_BAD_REQUEST);
        }

        $nouvelleFacture = ReviewInvoice::review($resultat['data']['nouvelle']->id);
        $envoiObr = (new ObrService)->addInvoice($nouvelleFacture->load(['company', 'customer', 'invoiceItems.product']));

        return response()->json([
            'success' => $envoiObr['success'],
            'message' => $envoiObr['success']
                ? "{$resultat['message']} et envoyée à l'OBR"
                : "{$resultat['message']}, mais l'envoi à l'OBR a échoué : ".($envoiObr['message'] ?? 'erreur inconnue').". Renvoyez-la depuis l'onglet Factures OBR.",
            'data' => [
                'ancienne' => $invoice->invoice_number,
                'nouvelle' => $nouvelleFacture->fresh(),
                'obr_result' => $envoiObr,
            ],
        ], $envoiObr['success'] ? Response::HTTP_OK : Response::HTTP_BAD_GATEWAY);
    }
}
