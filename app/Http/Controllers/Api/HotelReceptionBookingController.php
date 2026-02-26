<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReceptionBooking;
use App\Models\HotelReceptionHall;
use App\Models\InvoiceItem;
use App\Services\HotelInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HotelReceptionBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelReceptionBooking::with(['receptionHall', 'invoice'])
            ->orderBy('created_at', 'desc');

        if ($date = $request->input('date')) {
            $query->whereDate('booking_date', $date);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%");
            });
        }

        $bookings = $query->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $bookings->items(), 'meta' => [
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            'total' => $bookings->total(),
            'per_page' => $bookings->perPage(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_reception_hall_id' => 'required|exists:hotel_reception_halls,id',
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:30',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'nullable|string|max:255',
            'advance_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $booking = HotelReceptionBooking::create($validated);

        $hall = HotelReceptionHall::find($validated['hotel_reception_hall_id']);
        if ($hall && $hall->status === 'available') {
            $hall->update(['status' => 'reserved']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réservation créée avec succès',
            'data' => $booking->load('receptionHall'),
        ], Response::HTTP_CREATED);
    }

    public function generateInvoice(HotelReceptionBooking $hotelReceptionBooking): JsonResponse
    {
        try {
            $invoice = (new HotelInvoiceService)->generateReceptionInvoice($hotelReceptionBooking);

            return response()->json([
                'success' => true,
                'message' => 'Facture générée avec succès',
                'data' => ['invoice_id' => $invoice->id],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cancel(HotelReceptionBooking $hotelReceptionBooking): JsonResponse
    {
        $hotelReceptionBooking->update(['status' => 'cancelled']);

        $hall = $hotelReceptionBooking->receptionHall;
        if ($hall && ! $hall->activeBookings()->exists()) {
            $hall->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réservation annulée',
            'data' => $hotelReceptionBooking,
        ]);
    }

    /**
     * Extend a reception booking by extra hours and update the invoice.
     */
    public function extend(Request $request, HotelReceptionBooking $hotelReceptionBooking): JsonResponse
    {
        $validated = $request->validate([
            'extra_hours' => 'required|numeric|min:0.5',
        ]);

        $hall = $hotelReceptionBooking->receptionHall;
        $pricePerHour = (float) ($hall?->price_per_hour ?? 0);
        $extraAmount = round($validated['extra_hours'] * $pricePerHour, 2);

        DB::transaction(function () use ($hotelReceptionBooking, $validated, $extraAmount, $pricePerHour) {
            $newEndTime = \Carbon\Carbon::parse(
                $hotelReceptionBooking->booking_date->format('Y-m-d').' '.$hotelReceptionBooking->end_time
            )->addMinutes((int) ($validated['extra_hours'] * 60));

            $hotelReceptionBooking->update([
                'end_time' => $newEndTime->format('H:i:s'),
                'total_amount' => (float) $hotelReceptionBooking->total_amount + $extraAmount,
            ]);

            if ($hotelReceptionBooking->invoice_id) {
                $invoice = $hotelReceptionBooking->invoice;
                if ($invoice) {
                    $newTotal = (float) $invoice->invoice_total_amount + $extraAmount;
                    $invoice->update([
                        'invoice_total_amount' => $newTotal,
                        'invoice_amount_nvat' => $newTotal,
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_designation' => "Prolongation — {$validated['extra_hours']}h supplémentaire(s)",
                        'item_quantity' => $validated['extra_hours'],
                        'item_price' => $pricePerHour,
                        'item_ct' => 0,
                        'item_tl' => 0,
                        'item_ott_tax' => 0,
                        'item_tsce_tax' => 0,
                        'item_price_nvat' => $extraAmount,
                        'vat' => 0,
                        'item_price_wvat' => $extraAmount,
                        'item_total_amount' => $extraAmount,
                        'user_id' => auth()->id(),
                    ]);

                    $invoice->updatePaymentStatus();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Réservation prolongée de {$validated['extra_hours']}h",
            'data' => $hotelReceptionBooking->fresh(['receptionHall', 'invoice']),
            'extra_amount' => $extraAmount,
        ]);
    }
}
