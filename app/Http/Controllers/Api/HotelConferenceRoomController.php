<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelConferenceRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelConferenceRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelConferenceRoom::withCount(['activeBookings']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $rooms = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'floor' => 'nullable|string|max:10',
            'capacity' => 'required|integer|min:1',
            'price_per_hour' => 'required|numeric|min:0',
            'equipment' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $room = HotelConferenceRoom::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Salle créée avec succès',
            'data' => $room,
        ], Response::HTTP_CREATED);
    }

    public function show(HotelConferenceRoom $hotelConferenceRoom): JsonResponse
    {
        $hotelConferenceRoom->load(['activeBookings']);

        return response()->json(['success' => true, 'data' => $hotelConferenceRoom]);
    }

    public function update(Request $request, HotelConferenceRoom $hotelConferenceRoom): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'floor' => 'nullable|string|max:10',
            'capacity' => 'sometimes|integer|min:1',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:available,occupied,reserved,maintenance',
            'equipment' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $hotelConferenceRoom->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Salle mise à jour',
            'data' => $hotelConferenceRoom,
        ]);
    }

    public function destroy(HotelConferenceRoom $hotelConferenceRoom): JsonResponse
    {
        if ($hotelConferenceRoom->activeBookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une salle avec des réservations actives',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hotelConferenceRoom->delete();

        return response()->json(['success' => true, 'message' => 'Salle supprimée']);
    }
}
