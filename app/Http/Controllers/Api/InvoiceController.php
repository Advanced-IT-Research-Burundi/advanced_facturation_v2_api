<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\WarehouseProduct;
use App\Services\ObrService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    protected $stockService;

    protected $obrService;

    public function __construct(StockService $stockService, ObrService $obrService)
    {
        $this->stockService = $stockService;
        $this->obrService = $obrService;
    }

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $query = Invoice::with(['company', 'customer'])
            ->withCount('invoiceItems')
            ->withSum('payments', 'amount')
            ->orderBy('created_at', 'desc');

        // Filtre par type de facture
        if ($request->has('invoice_type') && $request->invoice_type !== 'all') {
            $query->where('invoice_type', $request->invoice_type);
        }

        // Filtre par statut OBR
        if ($request->has('obr_status') && $request->obr_status !== 'all') {
            $query->where('obr_submission_status', $request->obr_status);
        }

        // Filtre par statut de paiement
        if ($request->has('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        // Recherche par numéro de facture, nom client ou NIF client
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_TIN', 'like', "%{$search}%");
            });
        }

        // Filtre par date début
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('invoice_date', '>=', $request->start_date);
        }

        // Filtre par date fin
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('invoice_date', '<=', $request->end_date);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_type' => 'required|in:FN,FP,FA,FC,RC',
            'invoice_action' => 'required|in:SERVICE,POS',
            'invoice_currency' => 'required|string|max:3',
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'payment_type' => 'required|string|max:50',
            'reference_invoice_id' => 'nullable|exists:invoices,id',
            'reference_invoice_number' => 'nullable|string',
            'avoir_reason' => 'nullable|string',
            'refund_reason' => 'nullable|string',
            'deposit_reference' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.warehouse_product_id' => 'nullable|exists:warehouse_products,id',
            'items.*.item_designation' => 'required|string|max:255',
            'items.*.item_quantity' => 'required|numeric|min:0.01',
            'items.*.item_price' => 'required|numeric',
            'items.*.vat' => 'required|numeric|min:0|max:100',
            'items.*.item_ct' => 'nullable|numeric|min:0',
            'items.*.item_tl' => 'nullable|numeric|min:0',
        ]);

        if (
            $validated['invoice_action'] === 'POS'
            && $validated['invoice_type'] === 'FN'
            && ! in_array($validated['payment_type'], ['1', '2', '3', '4'], true)
        ) {
            throw ValidationException::withMessages([
                'payment_type' => 'Le mode de paiement doit être 1 (espèces), 2 (banque), 3 (à crédit) ou 4 (autres).',
            ]);
        }

        try {
            DB::beginTransaction();

            // Vérifier le stock uniquement si c'est une vente POS ET une facture réelle (FN)
            if ($validated['invoice_action'] === 'POS' && $validated['invoice_type'] === 'FN') {
                $stockCheck = $this->stockService->checkStockAvailability(
                    $validated['items'],
                    $validated['warehouse_id'] ?? null
                );

                if ($stockCheck['has_error']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock insuffisant pour certains articles',
                        'stock_details' => $stockCheck['items'],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $customer = Customer::findOrFail($validated['customer_id']);
            $company = auth()->user()->company;

            // Check if company is null and handle it (fallback or error)
            if (! $company) {
                // For POS, we might need a default company or just return error
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur n\'est associé à aucune entreprise configurée.',
                ], Response::HTTP_FORBIDDEN);
            }

            $totals = $this->calculateTotals($validated['items']);
            $cashRegister = null;

            if (
                $validated['invoice_action'] === 'POS'
                && $validated['invoice_type'] === 'FN'
                && $validated['payment_type'] === '1'
            ) {
                $cashRegister = CashRegister::where('company_id', $company->id)
                    ->where('status', 'open')
                    ->whereNull('hotel_section')
                    ->lockForUpdate()
                    ->first();

                if (! $cashRegister) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Ouvrez la caisse avant de créer une facture payée en espèces.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $invoice = Invoice::create([
                'invoice_number' => 'TEMP',
                'invoice_date' => now(),
                'invoice_type' => $validated['invoice_type'],
                'invoice_identifier' => $validated['invoice_action'],
                'invoice_currency' => $validated['invoice_currency'],
                'payment_type' => $validated['payment_type'] ?? '1',

                'tp_type' => $company->tp_type ?? 'PERSONNE MORALE',
                'tp_name' => $company->tp_name,
                'tp_TIN' => $company->tp_TIN,
                'tp_trade_number' => $company->tp_trade_number,
                'tp_phone_number' => $company->tp_phone_number,
                'tp_fiscal_center' => $company->tp_fiscal_center,
                'vat_taxpayer' => $company->vat_taxpayer,
                'ct_taxpayer' => $company->ct_taxpayer ?? '0',
                'tl_taxpayer' => $company->tl_taxpayer ?? '0',

                'customer_name' => $customer->customer_name,
                'customer_TIN' => $customer->customer_TIN,
                'customer_address' => $customer->customer_address,
                'vat_customer_payer' => $customer->vat_customer_payer,
                'invoice_amount_nvat' => $totals['total_ht'],
                'invoice_vat_amount' => $totals['total_vat'],
                'invoice_total_amount' => $totals['total_ttc'],
                // Références pour avoir et remboursement
                'reference_invoice_id' => $validated['reference_invoice_id'] ?? null,
                'cancelled_invoice_ref' => $validated['reference_invoice_number'] ?? null,
                'cn_motif' => $validated['avoir_reason'] ?? $validated['refund_reason'] ?? null,
                'deposit_reference' => $validated['deposit_reference'] ?? null,

                'obr_submission_status' => 'PENDING',

                // Initialize payment status
                'payment_status' => 'unpaid',
                'total_paid' => 0,

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

            $invoiceNumber = Invoice::getInvoiceNumber($invoice->id);
            $invoice->invoice_number = $invoiceNumber;
            $invoice->save();

            foreach ($validated['items'] as $item) {
                $itemCalculations = $this->calculateItemAmounts($item);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_designation' => $item['item_designation'],
                    'item_quantity' => $item['item_quantity'],
                    'item_price' => $item['item_price'],
                    'item_ct' => $item['item_ct'] ?? 0,
                    'item_tl' => $item['item_tl'] ?? 0,
                    'item_ott_tax' => 0,
                    'item_tsce_tax' => 0,
                    'item_price_nvat' => $itemCalculations['price_nvat'],
                    'vat' => $item['vat'],
                    'item_price_wvat' => $itemCalculations['price_wvat'],
                    'item_total_amount' => $itemCalculations['total_amount'],
                    'product_id' => $item['product_id'] ?? null,
                    'user_id' => auth()->id(),
                ]);
            }

            $stockMovements = null;
            if ($validated['invoice_action'] === 'POS' && $validated['invoice_type'] === 'FN') {
                $stockMovements = $this->stockService->processSaleStockMovement(
                    $validated['items'],
                    $invoice->id,
                    $invoiceNumber,
                    $validated['warehouse_id'] ?? null
                );
            }

            if (
                $validated['invoice_action'] === 'POS'
                && $validated['invoice_type'] === 'FN'
                && in_array($validated['payment_type'], ['1', '2'], true)
            ) {
                $paymentMethod = [
                    '1' => 'cash',
                    '2' => 'bank_transfer',
                ][$validated['payment_type']];

                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->invoice_total_amount,
                    'payment_date' => now(),
                    'payment_method' => $paymentMethod,
                    'reference' => $invoice->invoice_number,
                    'note' => 'Paiement enregistré lors de la création de la facture POS.',
                    'created_by' => auth()->id(),
                    'company_id' => $company->id,
                ]);

                if ($validated['payment_type'] === '1' && $cashRegister) {
                    CashMovement::create([
                        'cash_register_id' => $cashRegister->id,
                        'invoice_id' => $invoice->id,
                        'payment_id' => $payment->id,
                        'type' => 'income',
                        'amount' => $payment->amount,
                        'description' => "Vente en espèces - facture #{$invoice->invoice_number}",
                        'reference' => $invoice->invoice_number,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Facture créée avec succès',
                'data' => [
                    'invoice' => $invoice->load(['customer', 'invoiceItems']),
                    'stock_movements' => $stockMovements,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la facture ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
        return response()->json([
            'success' => true,
            // Les détails de facture sont utilisés par l'impression et les avoirs.
            // Les mouvements de stock ne sont pas nécessaires ici et certaines
            // installations historiques ne possèdent pas leur clé invoice_id.
            'data' => $invoice->load(['company', 'customer', 'invoiceItems', 'payments']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_date' => 'sometimes|date',
            'invoice_type' => 'sometimes|string',
            'invoice_currency' => 'sometimes|string|max:3',
            'customer_id' => 'sometimes|exists:customers,id',
            'items' => 'sometimes|array|min:1',
            'items.*.item_designation' => 'required_with:items|string',
            'items.*.item_quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.item_price' => 'required_with:items|numeric|min:0',
            'items.*.vat' => 'required_with:items|numeric|min:0',
        ]);

        DB::transaction(function () use ($invoice, $validated) {
            $invoice->update(collect($validated)->only([
                'invoice_currency',
                'customer_id',
                'invoice_type',
            ])->toArray());

            if (isset($validated['items'])) {
                $invoice->invoiceItems()->delete();

                $total_amount_nvat = 0;
                $total_vat_amount = 0;
                $total_amount = 0;

                foreach ($validated['items'] as $item) {
                    $itemCalculations = $this->calculateItemAmounts($item);

                    $invoice->invoiceItems()->create([
                        'item_designation' => $item['item_designation'],
                        'item_quantity' => $item['item_quantity'],
                        'item_price' => $item['item_price'],
                        'item_ct' => $item['item_ct'] ?? 0,
                        'item_tl' => $item['item_tl'] ?? 0,
                        'item_ott_tax' => 0,
                        'item_tsce_tax' => 0,
                        'item_price_nvat' => $itemCalculations['price_nvat'],
                        'vat' => $item['vat'],
                        'item_price_wvat' => $itemCalculations['price_wvat'],
                        'item_total_amount' => $itemCalculations['total_amount'],
                        'product_id' => $item['product_id'] ?? null,
                        'user_id' => auth()->id(),
                    ]);

                    $total_amount_nvat += $itemCalculations['price_nvat'] * $item['item_quantity'];
                    $total_vat_amount += $itemCalculations['vat_amount'] * $item['item_quantity'];
                    // Note: total_amount in calculations is line total TTC
                    $total_amount += $itemCalculations['total_amount'];
                }

                // Update invoice totals
                $invoice->update([
                    'invoice_amount_nvat' => $total_amount_nvat,
                    'invoice_vat_amount' => $total_vat_amount,
                    'invoice_total_amount' => $total_amount,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated successfully',
            'data' => $invoice->load(['company', 'customer', 'invoiceItems']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified invoice (soft delete).
     */
    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted invoice.
     */
    public function restore($id)
    {
        $invoice = Invoice::withTrashed()->findOrFail($id);
        $invoice->restore();

        return response()->json([
            'success' => true,
            'message' => 'Invoice restored successfully',
            'data' => $invoice,
        ], Response::HTTP_OK);
    }

    /**
     * Calculer les montants d'un item
     */
    private function calculateItemAmounts(array $item): array
    {
        $quantity = $item['item_quantity'];
        $priceHT = $item['item_price'];
        $vatRate = $item['vat'];

        $priceNVAT = $priceHT;
        $vatAmount = ($priceHT * $vatRate) / 100;
        $priceWVAT = $priceHT + $vatAmount;
        $totalAmount = $priceWVAT * $quantity;

        return [
            'price_nvat' => round($priceNVAT, 2),
            'vat_amount' => round($vatAmount, 2),
            'price_wvat' => round($priceWVAT, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * Calculer les totaux de la facture
     */
    private function calculateTotals(array $items): array
    {
        $totalHT = 0;
        $totalVAT = 0;

        foreach ($items as $item) {
            $calculations = $this->calculateItemAmounts($item);
            $totalHT += $calculations['price_nvat'] * $item['item_quantity'];
            $totalVAT += $calculations['vat_amount'] * $item['item_quantity'];
        }

        return [
            'total_ht' => round($totalHT, 2),
            'total_vat' => round($totalVAT, 2),
            'total_ttc' => round($totalHT + $totalVAT, 2),
        ];
    }

    /**
     * Synchroniser les factures en attente vers OBR (appelé par cron job)
     */
    public function syncPendingInvoices()
    {
        return response()->json([
            'success' => true,
            'message' => 'OBR désactivé. Aucune synchronisation effectuée.',
            'data' => ['total' => 0, 'success' => 0, 'failed' => 0, 'errors' => []],
        ], Response::HTTP_OK);
    }

    /**
     * Renvoyer une facture rejetée à OBR
     */
    public function resendToObr(Invoice $invoice)
    {
        return response()->json([
            'success' => false,
            'message' => 'OBR désactivé. Envoi non disponible.',
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Obtenir les statistiques de synchronisation OBR
     */
    public function obrStats()
    {
        $stats = [
            'pending' => Invoice::where('obr_submission_status', 'PENDING')
                ->whereIn('invoice_type', ['FN', 'FA', 'FC', 'RC'])
                ->count(),
            'accepted' => Invoice::where('obr_submission_status', 'ACCEPTED')->count(),
            'rejected' => Invoice::where('obr_submission_status', 'REJECTED')->count(),
            'recent_errors' => Invoice::where('obr_submission_status', 'REJECTED')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(['id', 'invoice_number', 'obr_response_message', 'updated_at']),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ], Response::HTTP_OK);
    }

    /**
     * Annuler une facture avec retour de stock
     */
    public function cancelInvoice(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'motif' => 'required|string|max:500',
            'restore_stock' => 'boolean',
        ]);

        // Vérifier si la facture peut être annulée
        if ($invoice->is_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Cette facture est déjà annulée',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();

            $requireObrCancel = false;

            // 1. Restaurer le stock si demandé et si c'était une vente POS
            $stockRestored = [];
            $shouldRestoreStock = $request->boolean('restore_stock', true);

            if ($shouldRestoreStock && $invoice->invoice_identifier === 'POS') {
                $stockRestored = $this->restoreStockFromInvoice($invoice, $validated['motif']);
            }

            // 2. Mettre à jour la facture
            $invoice->update([
                'is_cancelled' => true,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $validated['motif'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Facture annulée avec succès',
                'data' => [
                    'invoice' => $invoice->fresh(),
                    'stock_restored' => $stockRestored,
                    'obr_cancelled' => $requireObrCancel,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de l\'annulation de la facture.', [
                'invoice_id' => $invoice->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restaurer le stock après annulation de facture
     */
    private function restoreStockFromInvoice(Invoice $invoice, string $motif): array
    {
        $stockRestored = [];

        foreach ($invoice->invoiceItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            // Trouver le mouvement de stock original
            $originalMovement = StockMovement::where('item_movement_invoice_ref', $invoice->invoice_number)
                ->where('product_id', $item->product_id)
                ->whereIn('item_movement_type', ['VENTE', 'SN'])
                ->first();

            if ($originalMovement) {
                // Créer un mouvement de retour (ER = Entrée Retour)
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
                    'item_movement_type' => 'ER', // Entrée Retour
                    'item_movement_invoice_ref' => $invoice->invoice_number,
                    'item_movement_description' => "Retour après annulation - {$motif}",
                    'item_movement_date' => now(),
                    'obr_submission_status' => 'PENDING',
                    'company_id' => $invoice->company_id,
                    'user_id' => auth()->id(),
                    'created_by' => auth()->id(),
                    'created_by_id' => auth()->id(),
                ]);

                // Mettre à jour le stock dans l'entrepôt
                $warehouseProduct = WarehouseProduct::where('warehouse_id', $originalMovement->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($warehouseProduct) {
                    $warehouseProduct->increment('quantity', $item->item_quantity);
                }

                $stockRestored[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $originalMovement->item_designation,
                    'quantity_restored' => $item->item_quantity,
                    'warehouse_id' => $originalMovement->warehouse_id,
                    'movement_id' => $returnMovement->id,
                ];
            }
        }

        return $stockRestored;
    }
}
