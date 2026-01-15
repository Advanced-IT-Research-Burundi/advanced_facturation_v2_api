<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Customer;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use App\Services\StockService;


class InvoiceController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }
    /**
     * Display a listing of invoices.
     */
    public function index()
    {
        $query = Invoice::with(['company', 'customer', 'invoiceItems'])
            ->orderBy('created_at', 'desc');

        if (request()->has('invoice_type')) {
            $query->where('invoice_type', request('invoice_type'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created invoice.
     */
        public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_type' => 'required|in:FN,FP,FA,FC',
            'invoice_action' => 'required|in:SERVICE,POS',
            'invoice_currency' => 'required|string|max:3',
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required_if:invoice_action,POS|exists:products,id',
            'items.*.item_designation' => 'required|string|max:255',
            'items.*.item_quantity' => 'required|numeric|min:0.01',
            'items.*.item_price' => 'required|numeric|min:0',
            'items.*.vat' => 'required|numeric|min:0|max:100',
            'items.*.item_ct' => 'nullable|numeric|min:0',
            'items.*.item_tl' => 'nullable|numeric|min:0',
        ]);
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
                        'stock_details' => $stockCheck['items']
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $customer = Customer::findOrFail($validated['customer_id']);
            $company = auth()->user()->company;
            
            // Check if company is null and handle it (fallback or error)
            if (!$company) {
                // For POS, we might need a default company or just return error
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur n\'est associé à aucune entreprise configurée.'
                ], Response::HTTP_FORBIDDEN);
            }

            $totals = $this->calculateTotals($validated['items']);
            $invoiceNumber = $this->generateInvoiceNumber(
                $validated['invoice_type'],
                $validated['invoice_action']
            );

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'invoice_type' => $validated['invoice_type'],
                'invoice_identifier' => $validated['invoice_action'],
                'invoice_currency' => $validated['invoice_currency'],

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

                'obr_submission_status' => 'PENDING',

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

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
                    'product_id' => $item['product_id'] ?? null, // Added product_id persistence
                    'user_id' => auth()->id(),
                ]);
            }

            $stockMovements = null;
            // Ne traiter les mouvements de stock que pour les factures réelles (FN) POS
            if ($validated['invoice_action'] === 'POS' && $validated['invoice_type'] === 'FN') {
                $stockMovements = $this->stockService->processSaleStockMovement(
                    $validated['items'],
                    $invoice->id,
                    $invoiceNumber,
                    $validated['warehouse_id'] ?? null
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Facture créée avec succès',
                'data' => [
                    'invoice' => $invoice->load(['customer', 'invoiceItems']),
                    'stock_movements' => $stockMovements
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la facture',
                'error' => $e->getMessage()
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
            'data' => $invoice->load(['company', 'customer', 'invoiceItems', 'stockMovements'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'sometimes|string',
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

        DB::transaction(function () use ($invoice, $request, $validated) {
            // Update Invoice details
            $invoice->update($request->only([
                'invoice_currency', 
                'customer_id', 
                'invoice_type'
                // Add other fillable fields if needed
            ]));

            // Update Items if present
            if ($request->has('items')) {
                // Simplest approach: Delete all and recreate. 
                // Ensure to handle stock logic if this was a POS sale (but for Proforma FP it's fine).
                $invoice->invoiceItems()->delete();

                $total_amount_nvat = 0;
                $total_vat_amount = 0;
                $total_amount = 0;

                foreach ($request->items as $item) {
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
            'data' => $invoice->load(['company', 'customer', 'invoiceItems'])
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
            'message' => 'Invoice deleted successfully'
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
            'data' => $invoice
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
     * Générer le numéro de facture
     */
    private function generateInvoiceNumber(string $type, string $identifier): string
    {
        $year = now()->year;
        $prefix = "{$type}-{$identifier}";

        $lastInvoice = Invoice::where('invoice_number', 'LIKE', "{$prefix}-{$year}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }

}
