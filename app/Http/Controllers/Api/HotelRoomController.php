<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelRoom::where('company_id', auth()->user()->company_id)
            ->withCount(['activeReservations']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($floor = $request->input('floor')) {
            $query->where('floor', $floor);
        }

        $rooms = $query->orderBy('room_number')->get();

        return response()->json([
            'success' => true,
            'data' => $rooms,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => 'required|string|max:20',
            'name' => 'nullable|string|max:100',
            'type' => 'required|in:standard,double,suite,vip',
            'floor' => 'nullable|string|max:10',
            'capacity' => 'required|integer|min:1|max:20',
            'price_per_night' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        $room = HotelRoom::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Chambre créée avec succès',
            'data' => $room,
        ], Response::HTTP_CREATED);
    }

    public function show(HotelRoom $hotelRoom): JsonResponse
    {
        $hotelRoom->load(['activeReservations']);

        return response()->json([
            'success' => true,
            'data' => $hotelRoom,
        ]);
    }

    public function update(Request $request, HotelRoom $hotelRoom): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => 'sometimes|string|max:20',
            'name' => 'nullable|string|max:100',
            'type' => 'sometimes|in:standard,double,suite,vip',
            'floor' => 'nullable|string|max:10',
            'capacity' => 'sometimes|integer|min:1|max:20',
            'price_per_night' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:available,occupied,reserved,maintenance',
            'description' => 'nullable|string',
        ]);

        $hotelRoom->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Chambre mise à jour',
            'data' => $hotelRoom,
        ]);
    }

    public function destroy(HotelRoom $hotelRoom): JsonResponse
    {
        if ($hotelRoom->activeReservations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une chambre avec des réservations actives',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hotelRoom->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chambre supprimée',
        ]);
    }
}
