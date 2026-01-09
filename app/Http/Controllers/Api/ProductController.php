<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Product::with(['company', 'stockMovements'])->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code' => 'required|string|unique:products|max:255',
            'item_designation' => 'required|string|max:255',
            'item_measurement_unit' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'company_id' => 'required|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id();

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('company')
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load(['company', 'stockMovements', 'warehouseProducts'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'item_code' => 'sometimes|required|string|unique:products,item_code,' . $product->id . '|max:255',
            'item_designation' => 'sometimes|required|string|max:255',
            'item_measurement_unit' => 'sometimes|required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'vat_rate' => 'sometimes|required|numeric|min:0|max:100',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('company')
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified product (soft delete).
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'success' => true,
            'message' => 'Product restored successfully',
            'data' => $product
        ], Response::HTTP_OK);
    }
}
