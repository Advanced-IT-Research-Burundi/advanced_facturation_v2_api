<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReservation;
use App\Models\HotelRoom;
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

        $reservations = $query->orderBy('check_in_date', 'desc')->paginate(20);

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
            $hotelReservation->update([
                'status' => 'checked_out',
                'actual_check_out_at' => now(),
                'advance_payment' => $validated['advance_payment'] ?? $hotelReservation->advance_payment,
                'balance_due' => $hotelReservation->total_amount - ($validated['advance_payment'] ?? $hotelReservation->advance_payment),
            ]);
            $hotelReservation->room->updateStatusFromReservations();
        });

        return response()->json([
            'success' => true,
            'message' => 'Check-out effectué avec succès',
            'data' => $hotelReservation->load(['room', 'customer']),
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
}
