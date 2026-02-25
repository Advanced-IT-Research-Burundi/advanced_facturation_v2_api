<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelKitchenStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelKitchenStockController extends Controller
{
    public function index(): JsonResponse
    {
        $stocks = HotelKitchenStock::orderBy('name')->get()->map(function ($stock) {
            $stock->is_low_stock = $stock->isLowStock();

            return $stock;
        });

        return response()->json(['success' => true, 'data' => $stocks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'alert_threshold' => 'nullable|numeric|min:0',
        ]);

        $stock = HotelKitchenStock::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ingrédient ajouté',
            'data' => $stock,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, HotelKitchenStock $hotelKitchenStock): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:20',
            'alert_threshold' => 'nullable|numeric|min:0',
        ]);

        $hotelKitchenStock->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stock mis à jour',
            'data' => $hotelKitchenStock,
        ]);
    }

    public function destroy(HotelKitchenStock $hotelKitchenStock): JsonResponse
    {
        $hotelKitchenStock->delete();

        return response()->json(['success' => true, 'message' => 'Ingrédient supprimé']);
    }
}
