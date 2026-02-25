<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelConferenceBooking;
use App\Models\HotelConferenceRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelConferenceBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelConferenceBooking::with(['conferenceRoom'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'desc');

        if ($date = $request->input('date')) {
            $query->whereDate('booking_date', $date);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $bookings = $query->get();

        return response()->json(['success' => true, 'data' => $bookings]);
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
}
