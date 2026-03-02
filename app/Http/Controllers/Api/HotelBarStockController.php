<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelBarStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelBarStockController extends Controller
{
    public function index(): JsonResponse
    {
        $stocks = HotelBarStock::orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $stocks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'alert_threshold' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
        ]);

        $stock = HotelBarStock::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stock créé avec succès',
            'data' => $stock,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, HotelBarStock $hotelBarStock): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:50',
            'alert_threshold' => 'sometimes|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
        ]);

        $hotelBarStock->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stock mis à jour',
            'data' => $hotelBarStock->fresh(),
        ]);
    }

    public function destroy(HotelBarStock $hotelBarStock): JsonResponse
    {
        $hotelBarStock->delete();

        return response()->json(['success' => true, 'message' => 'Stock supprimé']);
    }
}
