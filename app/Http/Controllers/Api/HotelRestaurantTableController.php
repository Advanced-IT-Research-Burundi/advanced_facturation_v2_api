<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelRestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelRestaurantTableController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = HotelRestaurantTable::withCount(['activeOrders'])
            ->orderBy('number')
            ->get();

        return response()->json(['success' => true, 'data' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => 'required|string|max:20',
            'seats' => 'required|integer|min:1',
            'location' => 'nullable|string|max:100',
        ]);

        $table = HotelRestaurantTable::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Table créée avec succès',
            'data' => $table,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, HotelRestaurantTable $hotelRestaurantTable): JsonResponse
    {
        $validated = $request->validate([
            'number' => 'sometimes|string|max:20',
            'seats' => 'sometimes|integer|min:1',
            'location' => 'nullable|string|max:100',
            'status' => 'sometimes|in:free,occupied',
        ]);

        $hotelRestaurantTable->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Table mise à jour',
            'data' => $hotelRestaurantTable,
        ]);
    }

    public function destroy(HotelRestaurantTable $hotelRestaurantTable): JsonResponse
    {
        if ($hotelRestaurantTable->activeOrders()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une table avec des commandes actives',
            ], Response::HTTP_BAD_REQUEST);
        }

        $hotelRestaurantTable->delete();

        return response()->json(['success' => true, 'message' => 'Table supprimée']);
    }
}
