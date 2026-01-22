<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Customer;
use App\Services\ObrService;
use Illuminate\Support\Facades\DB;

class RestaurantInvoiceService
{
    protected ObrService $obrService;

    public function __construct(ObrService $obrService)
    {
        $this->obrService = $obrService;
    }

    public function generateInvoice(int $tableId, ?int $customerId = null, ?array $orderIds = null): Invoice
    {
        $table = RestaurantTable::findOrFail($tableId);
        $company = auth()->user()->company;

        $ordersQuery = RestaurantOrder::where('restaurant_table_id', $tableId)
            ->whereIn('status', ['open', 'served']);

        if ($orderIds) {
            $ordersQuery->whereIn('id', $orderIds);
        }

        $orders = $ordersQuery->with('items.product')->get();

        if ($orders->isEmpty()) {
            throw new \Exception('Aucune commande à facturer pour cette table');
        }

        $customer = $customerId ? Customer::find($customerId) : null;

        return DB::transaction(function () use ($table, $orders, $company, $customer) {
            $totals = $this->calculateTotals($orders);

            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
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

                'customer_name' => $customer?->customer_name ?? 'Client Restaurant',
                'customer_TIN' => $customer?->customer_TIN ?? '',
                'customer_address' => $customer?->customer_address ?? '',
                'vat_customer_payer' => $customer?->vat_customer_payer ?? '0',

                'invoice_amount_nvat' => $totals['subtotal'],
                'invoice_vat_amount' => $totals['tax'],
                'invoice_total_amount' => $totals['total'],

                'obr_submission_status' => 'PENDING',
                'payment_status' => 'unpaid',
                'total_paid' => 0,

                'company_id' => $company->id,
                'customer_id' => $customer?->id,
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
                    if ($item->status === 'cancelled') continue;

                    $vatRate = 18;
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

            $table->updateStatus();

            return $invoice->load(['customer', 'invoiceItems', 'restaurantTable', 'server']);
        });
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
