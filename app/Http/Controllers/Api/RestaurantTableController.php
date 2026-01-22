<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RestaurantTableController extends Controller
{
    public function index(Request $request)
    {
        $query = RestaurantTable::where('company_id', auth()->user()->company_id)
            ->withCount(['activeOrders']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $tables = $query->orderBy('table_number')->get();

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_number' => 'required|string|max:20',
            'capacity' => 'required|integer|min:1|max:50',
            'location' => 'nullable|string|max:100',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        $table = RestaurantTable::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Table créée avec succès',
            'data' => $table,
        ], Response::HTTP_CREATED);
    }

    public function show(RestaurantTable $restaurantTable)
    {
        $restaurantTable->load(['activeOrders.items.product', 'activeOrders.server']);

        return response()->json([
            'success' => true,
            'data' => $restaurantTable,
        ]);
    }

    public function update(Request $request, RestaurantTable $restaurantTable)
    {
        $validated = $request->validate([
            'table_number' => 'sometimes|string|max:20',
            'capacity' => 'sometimes|integer|min:1|max:50',
            'status' => 'sometimes|in:free,occupied,reserved',
            'location' => 'nullable|string|max:100',
        ]);

        $restaurantTable->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Table mise à jour',
            'data' => $restaurantTable,
        ]);
    }

    public function destroy(RestaurantTable $restaurantTable)
    {
        if ($restaurantTable->hasActiveOrders()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une table avec des commandes actives',
            ], Response::HTTP_BAD_REQUEST);
        }

        $restaurantTable->delete();

        return response()->json([
            'success' => true,
            'message' => 'Table supprimée',
        ]);
    }

    public function updateStatus(Request $request, RestaurantTable $restaurantTable)
    {
        $validated = $request->validate([
            'status' => 'required|in:free,occupied,reserved',
        ]);

        $restaurantTable->update($validated);

        return response()->json([
            'success' => true,
            'data' => $restaurantTable,
        ]);
    }
}
