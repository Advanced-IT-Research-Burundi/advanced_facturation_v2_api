<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelDish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelDishController extends Controller
{
    public function index(): JsonResponse
    {
        $dishes = HotelDish::orderBy('category')->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $dishes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'prep_time' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'available' => 'sometimes|boolean',
        ]);

        $dish = HotelDish::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plat ajouté',
            'data' => $dish,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, HotelDish $hotelDish): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:50',
            'price' => 'sometimes|numeric|min:0',
            'prep_time' => 'nullable|integer|min:0',
            'ingredients' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'available' => 'sometimes|boolean',
        ]);

        $hotelDish->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plat mis à jour',
            'data' => $hotelDish,
        ]);
    }

    public function destroy(HotelDish $hotelDish): JsonResponse
    {
        $hotelDish->delete();

        return response()->json(['success' => true, 'message' => 'Plat supprimé']);
    }
}
