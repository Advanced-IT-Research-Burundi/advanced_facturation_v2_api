<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReservation;
use App\Models\HotelRoom;
use App\Models\Invoice;
use App\Services\HotelInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HotelReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelReservation::where('company_id', auth()->user()->company_id)
            ->with(['room', 'customer']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($roomId = $request->input('room_id')) {
            $query->where('hotel_room_id', $roomId);
        }

        if ($date = $request->input('date')) {
            $query->whereDate('check_in_date', '<=', $date)
                ->whereDate('check_out_date', '>=', $date);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        $reservations = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_room_id' => 'required|exists:hotel_rooms,id',
            'customer_id' => 'nullable|exists:customers,id',
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:30',
            'guest_email' => 'nullable|email|max:255',
            'guest_id_number' => 'nullable|string|max:100',
            'guest_id_type' => 'nullable|in:cni,passport',
            'guest_address' => 'nullable|string|max:500',
            'guest_birthplace' => 'nullable|string|max:255',
            'guest_birthdate' => 'nullable|date',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'advance_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $room = HotelRoom::findOrFail($validated['hotel_room_id']);

        if (! $room->isAvailableForDates($validated['check_in_date'], $validated['check_out_date'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette chambre n\'est pas disponible pour ces dates',
            ], Response::HTTP_BAD_REQUEST);
        }

        $checkIn = new \Carbon\Carbon($validated['check_in_date']);
        $checkOut = new \Carbon\Carbon($validated['check_out_date']);
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $totalAmount = $room->price_per_night * $nights;
        $advancePayment = $validated['advance_payment'] ?? 0;

        $validated['company_id'] = auth()->user()->company_id;
        $validated['nights'] = $nights;
        $validated['price_per_night'] = $room->price_per_night;
        $validated['total_amount'] = $totalAmount;
        $validated['advance_payment'] = $advancePayment;
        $validated['balance_due'] = $totalAmount - $advancePayment;
        $validated['status'] = 'confirmed';

        $reservation = DB::transaction(function () use ($validated, $room) {
            $reservation = HotelReservation::create($validated);

            if ($reservation->check_in_date->isToday()) {
                $room->update(['status' => 'reserved']);
            }

            return $reservation;
        });

        $reservation->load(['room', 'customer']);

        return response()->json([
            'success' => true,
            'message' => 'Réservation créée avec succès',
            'data' => $reservation,
        ], Response::HTTP_CREATED);
    }

    public function show(HotelReservation $hotelReservation): JsonResponse
    {
        $hotelReservation->load(['room', 'customer', 'invoice']);

        return response()->json([
            'success' => true,
            'data' => $hotelReservation,
        ]);
    }

    public function update(Request $request, HotelReservation $hotelReservation): JsonResponse
    {
        $validated = $request->validate([
            'guest_name' => 'sometimes|string|max:255',
            'guest_phone' => 'nullable|string|max:30',
            'guest_email' => 'nullable|email|max:255',
            'guest_id_number' => 'nullable|string|max:100',
            'guest_id_type' => 'nullable|in:cni,passport',
            'guest_address' => 'nullable|string|max:500',
            'guest_birthplace' => 'nullable|string|max:255',
            'guest_birthdate' => 'nullable|date',
            'check_in_date' => 'sometimes|date',
            'check_out_date' => 'sometimes|date|after:check_in_date',
            'advance_payment' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        if (isset($validated['check_in_date']) || isset($validated['check_out_date'])) {
            $checkIn = new \Carbon\Carbon($validated['check_in_date'] ?? $hotelReservation->check_in_date);
            $checkOut = new \Carbon\Carbon($validated['check_out_date'] ?? $hotelReservation->check_out_date);

            if (! $hotelReservation->room->isAvailableForDates(
                $checkIn->toDateString(),
                $checkOut->toDateString(),
                $hotelReservation->id
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette chambre n\'est pas disponible pour ces dates',
                ], Response::HTTP_BAD_REQUEST);
            }

            $nights = max(1, $checkIn->diffInDays($checkOut));
            $validated['nights'] = $nights;
            $validated['total_amount'] = $hotelReservation->price_per_night * $nights;
            $validated['balance_due'] = $validated['total_amount'] - ($validated['advance_payment'] ?? $hotelReservation->advance_payment);
        } elseif (isset($validated['advance_payment'])) {
            $validated['balance_due'] = $hotelReservation->total_amount - $validated['advance_payment'];
        }

        $hotelReservation->update($validated);

        if (isset($validated['advance_payment'])) {
            $this->syncInvoicePaymentStatus($hotelReservation, (float) $validated['advance_payment']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Réservation mise à jour',
            'data' => $hotelReservation->load(['room', 'customer']),
        ]);
    }

    public function destroy(HotelReservation $hotelReservation): JsonResponse
    {
        if (in_array($hotelReservation->status, ['checked_in'])) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une réservation en cours',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hotelReservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Réservation supprimée',
        ]);
    }

    public function checkIn(HotelReservation $hotelReservation): JsonResponse
    {
        if ($hotelReservation->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les réservations confirmées peuvent être enregistrées',
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::transaction(function () use ($hotelReservation) {
            $hotelReservation->update([
                'status' => 'checked_in',
                'actual_check_in_at' => now(),
            ]);
            $hotelReservation->room->update(['status' => 'occupied']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Check-in effectué avec succès',
            'data' => $hotelReservation->load(['room', 'customer']),
        ]);
    }

    public function checkOut(Request $request, HotelReservation $hotelReservation): JsonResponse
    {
        if ($hotelReservation->status !== 'checked_in') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les réservations en cours peuvent effectuer un check-out',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'advance_payment' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($hotelReservation, $validated) {
            $totalPaid = (float) ($validated['advance_payment'] ?? $hotelReservation->advance_payment);

            $hotelReservation->update([
                'status' => 'checked_out',
                'actual_check_out_at' => now(),
                'advance_payment' => $totalPaid,
                'balance_due' => $hotelReservation->total_amount - $totalPaid,
            ]);

            $hotelReservation->room->updateStatusFromReservations();

            $this->syncInvoicePaymentStatus($hotelReservation, $totalPaid);
        });

        return response()->json([
            'success' => true,
            'message' => 'Check-out effectué avec succès',
            'data' => $hotelReservation->load(['room', 'customer']),
        ]);
    }

    private function syncInvoicePaymentStatus(HotelReservation $reservation, float $totalPaid): void
    {
        $invoice = Invoice::where('hotel_reservation_id', $reservation->id)->first();

        if (! $invoice) {
            return;
        }

        $service = new HotelInvoiceService;
        $status = $service->resolvePaymentStatus($totalPaid, (float) $invoice->invoice_total_amount);

        $invoice->update([
            'total_paid' => $totalPaid,
            'payment_status' => $status,
        ]);
    }

    public function cancel(HotelReservation $hotelReservation): JsonResponse
    {
        if ($hotelReservation->status === 'checked_in') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'annuler une réservation en cours',
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::transaction(function () use ($hotelReservation) {
            $hotelReservation->update(['status' => 'cancelled']);
            $hotelReservation->room->updateStatusFromReservations();
        });

        return response()->json([
            'success' => true,
            'message' => 'Réservation annulée',
            'data' => $hotelReservation->load(['room']),
        ]);
    }

    /**
     * Extend a reservation by adding extra nights and updating the invoice.
     */
    public function extend(Request $request, HotelReservation $hotelReservation): JsonResponse
    {
        $validated = $request->validate([
            'extra_nights' => 'required|integer|min:1',
        ]);

        if (! in_array($hotelReservation->status, ['reserved', 'checked_in'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être prolongée',
            ], Response::HTTP_BAD_REQUEST);
        }

        $extraNights = $validated['extra_nights'];
        $pricePerNight = (float) $hotelReservation->price_per_night;
        $extraAmount = $extraNights * $pricePerNight;

        DB::transaction(function () use ($hotelReservation, $extraNights, $extraAmount) {
            $newCheckOut = \Carbon\Carbon::parse($hotelReservation->check_out_date)
                ->addDays($extraNights);

            $hotelReservation->update([
                'check_out_date' => $newCheckOut->toDateString(),
                'nights' => $hotelReservation->nights + $extraNights,
                'total_amount' => (float) $hotelReservation->total_amount + $extraAmount,
            ]);

            if ($hotelReservation->invoice_id) {
                $invoice = $hotelReservation->invoice;
                if ($invoice) {
                    $newTotal = (float) $invoice->invoice_total_amount + $extraAmount;
                    $invoice->update([
                        'invoice_total_amount' => $newTotal,
                        'invoice_amount_nvat' => $newTotal,
                    ]);

                    \App\Models\InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_designation' => "Prolongation — {$extraNights} nuit(s) supplémentaire(s)",
                        'item_quantity' => $extraNights,
                        'item_price' => $pricePerNight,
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
            'message' => "Réservation prolongée de {$extraNights} nuit(s)",
            'data' => $hotelReservation->fresh(['room', 'invoice']),
            'extra_amount' => $extraAmount,
        ]);
    }
}
