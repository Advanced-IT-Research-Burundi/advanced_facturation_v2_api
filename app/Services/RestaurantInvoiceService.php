<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Customer;
use App\Models\WarehouseProduct;
use App\Models\StockMovement;
use App\Services\ObrService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Exception;

class RestaurantInvoiceService
{
    protected ObrService $obrService;
    protected StockService $stockService;

    public function __construct(ObrService $obrService, StockService $stockService)
    {
        $this->obrService = $obrService;
        $this->stockService = $stockService;
    }

    public function generateInvoice(int $tableId, int $customerId, int $warehouseId, ?array $orderIds = null): Invoice
    {
        $table = RestaurantTable::findOrFail($tableId);
        $company = auth()->user()->company;
        $customer = Customer::findOrFail($customerId);

        $ordersQuery = RestaurantOrder::where('restaurant_table_id', $tableId)
            ->whereIn('status', ['open', 'served']);

        if ($orderIds) {
            $ordersQuery->whereIn('id', $orderIds);
        }

        $orders = $ordersQuery->with('items.product')->get();

        if ($orders->isEmpty()) {
            throw new Exception('Aucune commande à facturer pour cette table');
        }

        // Collect all items for stock verification
        $allItems = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->status === 'cancelled') {
                    continue;
                }
                $allItems[] = [
                    'product_id' => $item->product_id,
                    'item_quantity' => $item->quantity,
                    'item_price' => $item->unit_price,
                    'product' => $item->product,
                ];
            }
        }

        // Verify stock availability before proceeding
        $this->verifyStockAvailability($allItems, $warehouseId);

        return DB::transaction(function () use ($table, $orders, $company, $customer, $warehouseId, $allItems) {
            $totals = $this->calculateTotals($orders);
            $invoiceNumber = $this->generateInvoiceNumber();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'invoice_type' => 'FN',
                'invoice_identifier' => 'RESTAURANT',
                'invoice_currency' => 'BIF',

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
                'customer_TIN' => $customer->customer_TIN ?? '',
                'customer_address' => $customer->customer_address ?? '',
                'vat_customer_payer' => $customer->vat_customer_payer ?? '0',

                'invoice_amount_nvat' => $totals['subtotal'],
                'invoice_vat_amount' => $totals['tax'],
                'invoice_total_amount' => $totals['total'],

                'obr_submission_status' => 'PENDING',
                'payment_status' => 'unpaid',
                'total_paid' => 0,

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouseId,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),

                'restaurant_table_id' => $table->id,
                'server_id' => $orders->first()->server_id,
                'is_restaurant' => true,
                'restaurant_order_ids' => $orders->pluck('id')->toArray(),
            ]);

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    if ($item->status === 'cancelled') {
                        continue;
                    }

                    $vatRate = $item->product->vat ?? 18;
                    $priceHT = $item->unit_price;
                    $vatAmount = ($priceHT * $vatRate) / 100;
                    $priceTTC = $priceHT + $vatAmount;

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_designation' => $item->product->item_designation ?? $item->product->name,
                        'item_quantity' => $item->quantity,
                        'item_price' => $priceHT,
                        'item_ct' => 0,
                        'item_tl' => 0,
                        'item_ott_tax' => 0,
                        'item_tsce_tax' => 0,
                        'item_price_nvat' => $priceHT,
                        'vat' => $vatRate,
                        'item_price_wvat' => $priceTTC,
                        'item_total_amount' => $priceTTC * $item->quantity,
                        'product_id' => $item->product_id,
                        'user_id' => auth()->id(),
                    ]);
                }

                $order->markAsInvoiced();
            }

            // Process stock movements - deduct stock and create movement records
            $this->processStockMovements($allItems, $warehouseId, $invoiceNumber);

            $table->updateStatus();

            return $invoice->load(['customer', 'invoiceItems', 'restaurantTable', 'server']);
        });
    }

    /**
     * Verify stock availability for all items
     */
    private function verifyStockAvailability(array $items, int $warehouseId): void
    {
        $errors = [];

        foreach ($items as $item) {
            $warehouseProduct = WarehouseProduct::where('product_id', $item['product_id'])
                ->where('warehouse_id', $warehouseId)
                ->first();

            $available = $warehouseProduct ? $warehouseProduct->quantity : 0;
            $productName = $item['product']->name ?? $item['product']->item_designation;

            if ($available < $item['item_quantity']) {
                $errors[] = "{$productName}: stock insuffisant (disponible: {$available}, demandé: {$item['item_quantity']})";
            }
        }

        if (!empty($errors)) {
            throw new Exception('Stock insuffisant: ' . implode('; ', $errors));
        }
    }

    /**
     * Process stock movements for all items
     */
    private function processStockMovements(array $items, int $warehouseId, string $invoiceNumber): void
    {
        foreach ($items as $item) {
            $product = $item['product'];

            // Create stock movement record
            $movement = StockMovement::create([
                'system_or_device_id' => gethostname() . '-' . request()->ip(),
                'item_code' => $product->item_code ?? $product->id,
                'item_designation' => $product->item_designation ?? $product->name,
                'item_quantity' => $item['item_quantity'],
                'item_measurement_unit' => $product->item_measurement_unit ?? 'UNITE',
                'item_purchase_or_sale_price' => $item['item_price'],
                'item_purchase_or_sale_currency' => 'BIF',
                'item_movement_type' => 'SN', // Sortie Normale (Sale Normal)
                'item_movement_invoice_ref' => $invoiceNumber,
                'item_movement_description' => "Vente Restaurant - Facture {$invoiceNumber}",
                'item_movement_date' => now(),
                'obr_submission_status' => 'PENDING',
                'company_id' => auth()->user()->company_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'created_by' => auth()->id(),
                'user_id' => auth()->id(),
            ]);

            // Decrement stock quantity
            $warehouseProduct = WarehouseProduct::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($warehouseProduct) {
                $warehouseProduct->decrement('quantity', $item['item_quantity']);
                $warehouseProduct->update(['last_stock_movement_id' => $movement->id]);
            }
        }
    }

    public function payInvoice(int $invoiceId, string $paymentMethod, ?float $amount = null): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if (!$invoice->is_restaurant) {
            throw new \Exception('Cette facture n\'est pas une facture restaurant');
        }

        $paidAmount = $amount ?? $invoice->invoice_total_amount;

        $invoice->update([
            'payment_status' => $paidAmount >= $invoice->invoice_total_amount ? 'paid' : 'partial',
            'total_paid' => $paidAmount,
            'payment_method' => $paymentMethod,
        ]);

        return $invoice->fresh(['customer', 'invoiceItems', 'restaurantTable', 'server']);
    }

    public function sendToObr(int $invoiceId): array
    {
        $invoice = Invoice::with(['company', 'invoiceItems'])->findOrFail($invoiceId);

        if ($invoice->obr_submission_status === 'ACCEPTED') {
            return [
                'success' => false,
                'message' => 'Cette facture a déjà été envoyée à OBR',
            ];
        }

        $result = $this->obrService->addInvoice($invoice, $invoice->company);

        if ($result['success']) {
            $invoice->update([
                'obr_submission_status' => 'ACCEPTED',
                'obr_invoice_identifier' => $result['invoice_identifier'] ?? null,
                'obr_invoice_registered_number' => $result['invoice_registered_number'] ?? null,
                'obr_invoice_registered_date' => $result['invoice_registered_date'] ?? null,
                'obr_electronic_signature' => $result['electronic_signature'] ?? null,
                'obr_sent_at' => now(),
                'obr_response_message' => $result['message'] ?? null,
            ]);
        } else {
            $invoice->update([
                'obr_submission_status' => 'REJECTED',
                'obr_response_message' => $result['message'] ?? 'Erreur inconnue',
            ]);
        }

        return $result;
    }

    private function calculateTotals($orders): array
    {
        $subtotal = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->status !== 'cancelled') {
                    $subtotal += $item->total_price;
                }
            }
        }

        $tax = $subtotal * 0.18;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $tax, 2),
        ];
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $prefix = "FN-RESTO-{$year}";

        $last = Invoice::where('invoice_number', 'LIKE', "{$prefix}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        $number = $last ? ((int)substr($last->invoice_number, -4) + 1) : 1;

        return sprintf('%s-%04d', $prefix, $number);
    }

    public function getTableInvoices(int $tableId): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('restaurant_table_id', $tableId)
            ->where('is_restaurant', true)
            ->with(['server', 'invoiceItems'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
