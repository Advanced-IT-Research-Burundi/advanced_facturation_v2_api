<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseProductController extends Controller
{
    /**
     * Display a listing of warehouse products.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => WarehouseProduct::with(['product', 'warehouse'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created warehouse product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'last_stock_movement_id' => 'nullable|exists:stock_movements,id',
        ]);

        $validated['user_id'] = auth()->id();

        // Check for unique constraint
        $existing = WarehouseProduct::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This product already exists in this warehouse'
            ], Response::HTTP_CONFLICT);
        }

        $warehouseProduct = WarehouseProduct::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse product created successfully',
            'data' => $warehouseProduct->load(['product', 'warehouse'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified warehouse product.
     */
    public function show(WarehouseProduct $warehouseProduct)
    {
        return response()->json([
            'success' => true,
            'data' => $warehouseProduct->load(['product', 'warehouse', 'lastStockMovement'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified warehouse product.
     */
    public function update(Request $request, WarehouseProduct $warehouseProduct)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|required|numeric|min:0',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|required|string|max:3',
            'last_stock_movement_id' => 'nullable|exists:stock_movements,id',
        ]);

        $warehouseProduct->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse product updated successfully',
            'data' => $warehouseProduct->load(['product', 'warehouse'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified warehouse product (soft delete).
     */
    public function destroy(WarehouseProduct $warehouseProduct)
    {
        $warehouseProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse product deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted warehouse product.
     */
    public function restore($id)
    {
        $warehouseProduct = WarehouseProduct::withTrashed()->findOrFail($id);
        $warehouseProduct->restore();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse product restored successfully',
            'data' => $warehouseProduct
        ], Response::HTTP_OK);
    }
}
