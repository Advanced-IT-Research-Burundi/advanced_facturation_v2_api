<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductUnitController extends Controller
{
    /**
     * Display a listing of product units.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ProductUnit::with('company')->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created product unit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            
        ]);

        $validated['company_id'] = auth()->user()->company_id ?? 1;

        $productUnit = ProductUnit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product unit created successfully',
            'data' => $productUnit->load('company')
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified product unit.
     */
    public function show(ProductUnit $productUnit)
    {
        return response()->json([
            'success' => true,
            'data' => $productUnit->load('company')
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified product unit.
     */
    public function update(Request $request, ProductUnit $productUnit)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $productUnit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product unit updated successfully',
            'data' => $productUnit->load('company')
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified product unit (soft delete).
     */
    public function destroy(ProductUnit $productUnit)
    {
        $productUnit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product unit deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted product unit.
     */
    public function restore($id)
    {
        $productUnit = ProductUnit::withTrashed()->findOrFail($id);
        $productUnit->restore();

        return response()->json([
            'success' => true,
            'message' => 'Product unit restored successfully',
            'data' => $productUnit
        ], Response::HTTP_OK);
    }
}
