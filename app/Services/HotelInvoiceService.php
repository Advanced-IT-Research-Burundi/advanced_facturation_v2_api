<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\HotelReservation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Exception;
use Illuminate\Support\Facades\DB;

class HotelInvoiceService
{
    public function generateInvoice(HotelReservation $reservation): Invoice
    {
        $reservation->load(['room', 'customer', 'company']);

        if ($reservation->invoice_id) {
            throw new Exception('Cette réservation a déjà une facture.');
        }

        $customer = $reservation->customer;
        if (! $customer) {
            $customer = $this->createCustomerFromReservation($reservation);
        }

        $company = $reservation->company ?? auth()->user()->company;
        if (! $company) {
            throw new Exception('Entreprise non trouvée.');
        }

        return DB::transaction(function () use ($reservation, $customer, $company) {
            $invoiceNumber = $this->generateInvoiceNumber();
            $room = $reservation->room;
            $designation = sprintf(
                'Hébergement - Chambre %s (%s), %d nuit(s) du %s au %s',
                $room->room_number,
                $this->getRoomTypeLabel($room->type),
                $reservation->nights,
                $reservation->check_in_date->format('d/m/Y'),
                $reservation->check_out_date->format('d/m/Y')
            );

            $totalHT = (float) $reservation->total_amount;
            $vatRate = 0;
            $totalTTC = $totalHT;

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now(),
                'invoice_type' => 'FN',
                'invoice_identifier' => 'HOTEL',
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

                'invoice_amount_nvat' => $totalHT,
                'invoice_vat_amount' => 0,
                'invoice_total_amount' => $totalTTC,

                'obr_submission_status' => 'PENDING',
                'payment_status' => 'unpaid',
                'total_paid' => 0,

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_designation' => $designation,
                'item_quantity' => 1,
                'item_price' => $totalHT,
                'item_ct' => 0,
                'item_tl' => 0,
                'item_ott_tax' => 0,
                'item_tsce_tax' => 0,
                'item_price_nvat' => $totalHT,
                'vat' => $vatRate,
                'item_price_wvat' => $totalTTC,
                'item_total_amount' => $totalTTC,
                'user_id' => auth()->id(),
            ]);

            $reservation->update(['invoice_id' => $invoice->id]);

            return $invoice->load(['customer', 'invoiceItems']);
        });
    }

    private function createCustomerFromReservation(HotelReservation $reservation): Customer
    {
        $company = $reservation->company ?? auth()->user()->company;
        $customer = Customer::create([
            'customer_name' => $reservation->guest_name,
            'customer_phone' => $reservation->guest_phone ?? '',
            'customer_address' => $reservation->guest_email ? "Email: {$reservation->guest_email}" : '',
            'customer_TIN' => null,
            'vat_customer_payer' => '0',
            'company_id' => $company->id,
            'user_id' => auth()->id(),
        ]);
        $reservation->update(['customer_id' => $customer->id]);

        return $customer;
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $prefix = "FN-HOTEL-{$year}";

        $last = Invoice::where('invoice_number', 'LIKE', "{$prefix}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        $number = $last ? ((int) substr($last->invoice_number, -4) + 1) : 1;

        return sprintf('%s-%04d', $prefix, $number);
    }

    private function getRoomTypeLabel(?string $type): string
    {
        $labels = [
            'standard' => 'Standard',
            'double' => 'Double',
            'suite' => 'Suite',
            'vip' => 'VIP',
        ];

        return $labels[$type ?? ''] ?? $type ?? '—';
    }
}
