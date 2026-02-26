<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelReceptionHall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelReceptionHallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelReceptionHall::withCount(['activeBookings']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $halls = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $halls]);
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

        $hall = HotelReceptionHall::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Salle de réception créée avec succès',
            'data' => $hall,
        ], Response::HTTP_CREATED);
    }

    public function show(HotelReceptionHall $hotelReceptionHall): JsonResponse
    {
        $hotelReceptionHall->load(['activeBookings']);

        return response()->json(['success' => true, 'data' => $hotelReceptionHall]);
    }

    public function update(Request $request, HotelReceptionHall $hotelReceptionHall): JsonResponse
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

        $hotelReceptionHall->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Salle mise à jour',
            'data' => $hotelReceptionHall,
        ]);
    }

    public function destroy(HotelReceptionHall $hotelReceptionHall): JsonResponse
    {
        if ($hotelReceptionHall->activeBookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une salle avec des réservations actives',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hotelReceptionHall->delete();

        return response()->json(['success' => true, 'message' => 'Salle supprimée']);
    }
}
