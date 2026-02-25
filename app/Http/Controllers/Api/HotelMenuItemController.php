<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotelMenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HotelMenuItem::with('barStock');

        if ($request->boolean('available_only')) {
            $query->where('available', true);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $items = $query->orderBy('category')->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'available' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'bar_stock_id' => 'nullable|exists:hotel_bar_stocks,id',
            'stock_per_serving' => 'nullable|numeric|min:0',
        ]);

        $item = HotelMenuItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Article ajouté au menu',
            'data' => $item->load('barStock'),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, HotelMenuItem $hotelMenuItem): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:50',
            'price' => 'sometimes|numeric|min:0',
            'available' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'bar_stock_id' => 'nullable|exists:hotel_bar_stocks,id',
            'stock_per_serving' => 'nullable|numeric|min:0',
        ]);

        $hotelMenuItem->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Article mis à jour',
            'data' => $hotelMenuItem->fresh()->load('barStock'),
        ]);
    }

    public function destroy(HotelMenuItem $hotelMenuItem): JsonResponse
    {
        $hotelMenuItem->delete();

        return response()->json(['success' => true, 'message' => 'Article supprimé']);
    }
}
