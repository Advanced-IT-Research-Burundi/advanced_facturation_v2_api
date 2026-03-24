<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\HotelConferenceBooking;
use App\Models\HotelReceptionBooking;
use App\Models\HotelReservation;
use App\Models\HotelRestaurantOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
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
            $room = $reservation->room;
            $roomNumber = $room?->room_number ?? 'N/A';
            $roomType = $room ? $this->getRoomTypeLabel($room->type) : '—';
            $designation = sprintf(
                'Hébergement - Chambre %s (%s), %d nuit(s) du %s au %s',
                $roomNumber,
                $roomType,
                $reservation->nights,
                $reservation->check_in_date->format('d/m/Y'),
                $reservation->check_out_date->format('d/m/Y')
            );

            $totalHT = (float) $reservation->total_amount;
            $vatRate = 0;
            $totalTTC = $totalHT;

            $invoice = Invoice::create([
                'invoice_number' => 'TEMP',
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
                'payment_status' => $this->resolvePaymentStatus((float) $reservation->advance_payment, $totalTTC),
                'total_paid' => (float) $reservation->advance_payment,

                'hotel_reservation_id' => $reservation->id,
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

            $invoice->invoice_number = Invoice::getInvoiceNumber($invoice->id);
            $invoice->save();

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

            if ((float) $reservation->advance_payment > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => (float) $reservation->advance_payment,
                    'payment_date' => now(),
                    'payment_method' => 'cash',
                    'note' => 'Avance à la réservation',
                    'created_by' => auth()->id(),
                    'company_id' => $company->id,
                ]);
            }

            return $invoice->load(['customer', 'invoiceItems']);
        });
    }

    public function generateConferenceInvoice(HotelConferenceBooking $booking): Invoice
    {
        $booking->load(['conferenceRoom', 'company']);

        if ($booking->invoice_id) {
            throw new Exception('Cette réservation a déjà une facture.');
        }

        $company = $booking->company ?? auth()->user()->company;
        if (! $company) {
            throw new Exception('Entreprise non trouvée.');
        }

        $customer = $this->createCustomerFromBooking($booking, $company);

        return DB::transaction(function () use ($booking, $customer, $company) {
            $room = $booking->conferenceRoom;
            $pricePerHour = (float) ($room?->price_per_hour ?? 0);

            $start = \Carbon\Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->start_time);
            $end = \Carbon\Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->end_time);
            $hours = max(0, round($start->diffInMinutes($end) / 60, 2));
            $totalHT = round($hours * $pricePerHour, 2);

            $designation = sprintf(
                'Réunion - Salle %s, %.1f heure(s) le %s de %s à %s%s',
                $room?->name ?? 'N/A',
                $hours,
                $booking->booking_date->format('d/m/Y'),
                substr($booking->start_time, 0, 5),
                substr($booking->end_time, 0, 5),
                $booking->purpose ? " ({$booking->purpose})" : ''
            );

            $invoice = Invoice::create([
                'invoice_number' => 'TEMP',
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
                'invoice_total_amount' => $totalHT,

                'obr_submission_status' => 'PENDING',
                'payment_status' => $this->resolvePaymentStatus((float) $booking->advance_payment, $totalHT),
                'total_paid' => (float) $booking->advance_payment,

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

            $invoice->invoice_number = Invoice::getInvoiceNumber($invoice->id);
            $invoice->save();

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
                'vat' => 0,
                'item_price_wvat' => $totalHT,
                'item_total_amount' => $totalHT,
                'user_id' => auth()->id(),
            ]);

            $booking->update([
                'invoice_id' => $invoice->id,
                'total_amount' => $totalHT,
            ]);

            if ((float) $booking->advance_payment > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => (float) $booking->advance_payment,
                    'payment_date' => now(),
                    'payment_method' => 'cash',
                    'note' => 'Avance à la réservation',
                    'created_by' => auth()->id(),
                    'company_id' => $company->id,
                ]);
            }

            return $invoice->load(['customer', 'invoiceItems']);
        });
    }

    public function generateReceptionInvoice(HotelReceptionBooking $booking): Invoice
    {
        $booking->load(['receptionHall', 'company']);

        if ($booking->invoice_id) {
            throw new Exception('Cette réservation a déjà une facture.');
        }

        $company = $booking->company ?? auth()->user()->company;
        if (! $company) {
            throw new Exception('Entreprise non trouvée.');
        }

        $customer = $this->createCustomerFromReceptionBooking($booking, $company);

        return DB::transaction(function () use ($booking, $customer, $company) {
            $hall = $booking->receptionHall;
            $pricePerHour = (float) ($hall?->price_per_hour ?? 0);

            $start = \Carbon\Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->start_time);
            $end = \Carbon\Carbon::parse($booking->booking_date->format('Y-m-d').' '.$booking->end_time);
            $hours = max(0, round($start->diffInMinutes($end) / 60, 2));
            $totalHT = round($hours * $pricePerHour, 2);

            $designation = sprintf(
                'Événement - Salle %s, %.1f heure(s) le %s de %s à %s%s',
                $hall?->name ?? 'N/A',
                $hours,
                $booking->booking_date->format('d/m/Y'),
                substr($booking->start_time, 0, 5),
                substr($booking->end_time, 0, 5),
                $booking->purpose ? " ({$booking->purpose})" : ''
            );

            $invoice = Invoice::create([
                'invoice_number' => 'TEMP',
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
                'invoice_total_amount' => $totalHT,

                'obr_submission_status' => 'PENDING',
                'payment_status' => $this->resolvePaymentStatus((float) $booking->advance_payment, $totalHT),
                'total_paid' => (float) $booking->advance_payment,

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),
            ]);

            $invoice->invoice_number = Invoice::getInvoiceNumber($invoice->id);
            $invoice->save();

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
                'vat' => 0,
                'item_price_wvat' => $totalHT,
                'item_total_amount' => $totalHT,
                'user_id' => auth()->id(),
            ]);

            $booking->update([
                'invoice_id' => $invoice->id,
                'total_amount' => $totalHT,
            ]);

            if ((float) $booking->advance_payment > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => (float) $booking->advance_payment,
                    'payment_date' => now(),
                    'payment_method' => 'cash',
                    'note' => 'Avance à la réservation',
                    'created_by' => auth()->id(),
                    'company_id' => $company->id,
                ]);
            }

            return $invoice->load(['customer', 'invoiceItems']);
        });
    }

    private function createCustomerFromReceptionBooking(HotelReceptionBooking $booking, $company): Customer
    {
        $phone = $booking->guest_phone ?? null;

        if ($phone) {
            $existing = Customer::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)
                ->where('customer_phone', $phone)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Customer::create([
            'customer_name' => $booking->guest_name,
            'customer_phone' => $phone,
            'customer_TIN' => null,
            'vat_customer_payer' => '0',
            'company_id' => $company->id,
            'user_id' => auth()->id(),
        ]);
    }

    private function createCustomerFromBooking(HotelConferenceBooking $booking, $company): Customer
    {
        $phone = $booking->guest_phone ?? null;

        if ($phone) {
            $existing = Customer::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)
                ->where('customer_phone', $phone)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Customer::create([
            'customer_name' => $booking->guest_name,
            'customer_phone' => $phone,
            'customer_TIN' => null,
            'vat_customer_payer' => '0',
            'company_id' => $company->id,
            'user_id' => auth()->id(),
        ]);
    }

    private function createCustomerFromReservation(HotelReservation $reservation): Customer
    {
        $company = $reservation->company ?? auth()->user()->company;
        $phone = $reservation->guest_phone ?? null;

        // If a phone number is provided, search globally (bypassing CompanyScope)
        // because customer_phone has a global unique constraint across all companies.
        if ($phone) {
            $existing = Customer::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)
                ->where('customer_phone', $phone)
                ->first();

            if ($existing) {
                $reservation->update(['customer_id' => $existing->id]);

                return $existing;
            }
        }

        $customer = Customer::create([
            'customer_name' => $reservation->guest_name,
            'customer_phone' => $phone,
            'customer_address' => $reservation->guest_email ? "Email: {$reservation->guest_email}" : null,
            'customer_TIN' => null,
            'vat_customer_payer' => '0',
            'company_id' => $company->id,
            'user_id' => auth()->id(),
        ]);

        $reservation->update(['customer_id' => $customer->id]);

        return $customer;
    }

    public function resolvePaymentStatus(float|string $paid, float|string $total): string
    {
        $paid = (float) $paid;
        $total = (float) $total;

        if ($total <= 0) {
            return 'paid';
        }

        if ($paid >= $total) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    public function generateRestaurantOrderInvoice(HotelRestaurantOrder $order): Invoice
    {
        if ($order->invoice_id) {
            return $order->invoice;
        }

        $order->load(['items', 'restaurantTable']);
        $company = auth()->user()->company;

        $clientName = $order->client_name
            ?: ($order->is_room_service ? 'Chambre '.($order->room_number ?? '—') : 'Table '.($order->restaurantTable?->number ?? '—'));

        $total = (float) $order->total;

        return DB::transaction(function () use ($order, $company, $clientName, $total) {
            $customer = Customer::where('customer_name', $clientName)
                ->where('company_id', $company->id)
                ->first()
                ?? Customer::create([
                    'customer_name' => $clientName,
                    'company_id' => $company->id,
                    'customer_TIN' => null,
                    'customer_phone' => null,
                    'customer_address' => '',
                    'vat_customer_payer' => '0',
                    'user_id' => auth()->id(),
                ]);

            $invoice = Invoice::create([
                'invoice_number' => 'TEMP',
                'invoice_date' => now(),
                'invoice_type' => 'FN',
                'invoice_identifier' => 'RESTAURANT',
                'invoice_currency' => 'BIF',

                'tp_type' => $company->tp_type ?? 'PERSONNE MORALE',
                'tp_name' => $company->tp_name,
                'tp_TIN' => $company->tp_TIN ?? '',
                'tp_trade_number' => $company->tp_trade_number ?? '',
                'tp_phone_number' => $company->tp_phone_number ?? '',
                'tp_fiscal_center' => $company->tp_fiscal_center ?? '',
                'vat_taxpayer' => $company->vat_taxpayer ?? '0',
                'ct_taxpayer' => $company->ct_taxpayer ?? '0',
                'tl_taxpayer' => $company->tl_taxpayer ?? '0',

                'customer_name' => $clientName,
                'customer_TIN' => '',
                'customer_address' => '',
                'vat_customer_payer' => '0',

                'invoice_amount_nvat' => $total,
                'invoice_vat_amount' => 0,
                'invoice_total_amount' => $total,

                'obr_submission_status' => 'PENDING',
                'payment_status' => 'paid',
                'total_paid' => $total,

                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'created_by_id' => auth()->id(),

                'is_restaurant' => true,
            ]);

            $invoice->invoice_number = Invoice::getInvoiceNumber($invoice->id);
            $invoice->save();

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_designation' => $item->name,
                    'item_quantity' => $item->qty,
                    'item_price' => $item->price,
                    'item_price_nvat' => $item->price,
                    'item_price_wvat' => $item->price,
                    'item_ct' => 0,
                    'item_tl' => 0,
                    'item_vat' => 0,
                    'vat' => 0,
                    'item_total_amount' => $item->price * $item->qty,
                    'user_id' => auth()->id(),
                ]);
            }

            $order->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
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
