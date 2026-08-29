<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelConferenceBooking;
use App\Models\HotelConferenceRoom;
use App\Models\InvoiceItem;
use App\Services\HotelInvoiceService;
use App\Services\ObrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HotelConferenceBookingController extends Controller
{
    public function __construct(
        protected HotelInvoiceService $hotelInvoiceService,
        protected ObrService $obrService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = HotelConferenceBooking::with(['conferenceRoom', 'invoice'])
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
            'hotel_conference_room_id' => 'required|exists:hotel_conference_rooms,id',
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:30',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'purpose' => 'nullable|string|max:255',
            'advance_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $booking = HotelConferenceBooking::create($validated);

        $room = HotelConferenceRoom::find($validated['hotel_conference_room_id']);
        if ($room && $room->status === 'available') {
            $room->update(['status' => 'reserved']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réservation créée avec succès',
            'data' => $booking->load('conferenceRoom'),
        ], Response::HTTP_CREATED);
    }

    public function generateInvoice(Request $request, HotelConferenceBooking $hotelConferenceBooking): JsonResponse
    {
        try {
            $invoice = $this->hotelInvoiceService->generateConferenceInvoice($hotelConferenceBooking);
            $obrResult = $this->obrService->sendInvoiceIfSuperAdmin($invoice, $request->user());

            return response()->json([
                'success' => true,
                'message' => $obrResult ? 'Facture générée avec succès. Envoi OBR direct traité.' : 'Facture générée avec succès. En attente d\'envoi OBR.',
                'data' => ['invoice_id' => $invoice->id],
                'obr_result' => $obrResult,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cancel(HotelConferenceBooking $hotelConferenceBooking): JsonResponse
    {
        $hotelConferenceBooking->update(['status' => 'cancelled']);

        $room = $hotelConferenceBooking->conferenceRoom;
        if ($room && ! $room->activeBookings()->exists()) {
            $room->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réservation annulée',
            'data' => $hotelConferenceBooking,
        ]);
    }

    /**
     * Extend a conference booking by extra hours and update the invoice.
     */
    public function extend(Request $request, HotelConferenceBooking $hotelConferenceBooking): JsonResponse
    {
        $validated = $request->validate([
            'extra_hours' => 'required|numeric|min:0.5',
        ]);

        $room = $hotelConferenceBooking->conferenceRoom;
        $pricePerHour = (float) ($room?->price_per_hour ?? 0);
        $extraAmount = round($validated['extra_hours'] * $pricePerHour, 2);

        DB::transaction(function () use ($hotelConferenceBooking, $validated, $extraAmount, $pricePerHour) {
            $newEndTime = \Carbon\Carbon::parse(
                $hotelConferenceBooking->booking_date->format('Y-m-d').' '.$hotelConferenceBooking->end_time
            )->addMinutes((int) ($validated['extra_hours'] * 60));

            $hotelConferenceBooking->update([
                'end_time' => $newEndTime->format('H:i:s'),
                'total_amount' => (float) $hotelConferenceBooking->total_amount + $extraAmount,
            ]);

            if ($hotelConferenceBooking->invoice_id) {
                $invoice = $hotelConferenceBooking->invoice;
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
            'data' => $hotelConferenceBooking->fresh(['conferenceRoom', 'invoice']),
            'extra_amount' => $extraAmount,
        ]);
    }
}
