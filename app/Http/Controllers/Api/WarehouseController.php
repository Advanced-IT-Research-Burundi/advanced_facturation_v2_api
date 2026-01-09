<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Warehouse::with(['company', 'stockMovements'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'company_id' => 'required|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id();

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successfully',
            'data' => $warehouse->load('company')
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified warehouse.
     */
    public function show(Warehouse $warehouse)
    {
        return response()->json([
            'success' => true,
            'data' => $warehouse->load(['company', 'stockMovements', 'warehouseProducts'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified warehouse.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:255',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse->load('company')
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified warehouse (soft delete).
     */
    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted warehouse.
     */
    public function restore($id)
    {
        $warehouse = Warehouse::withTrashed()->findOrFail($id);
        $warehouse->restore();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse restored successfully',
            'data' => $warehouse
        ], Response::HTTP_OK);
    }
}
