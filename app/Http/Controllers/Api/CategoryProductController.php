<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoryProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryProductController extends Controller
{
    /**
     * Display a listing of category products.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => CategoryProduct::latest()->paginate(15)
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created category product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $validated['user_id'] = auth()->id();

        $categoryProduct = CategoryProduct::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category product created successfully',
            'data' => $categoryProduct->load(['company', 'user'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified category product.
     */
    public function show(CategoryProduct $categoryProduct)
    {
        return response()->json([
            'success' => true,
            'data' => $categoryProduct->load(['company', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified category product.
     */
    public function update(Request $request, CategoryProduct $categoryProduct)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'sometimes|required|exists:companies,id',
        ]);

        $categoryProduct->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category product updated successfully',
            'data' => $categoryProduct->load(['company', 'user'])
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified category product (soft delete).
     */
    public function destroy(CategoryProduct $categoryProduct)
    {
        $categoryProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category product deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted category product.
     */
    public function restore($id)
    {
        $categoryProduct = CategoryProduct::withTrashed()->findOrFail($id);
        $categoryProduct->restore();

        return response()->json([
            'success' => true,
            'message' => 'Category product restored successfully',
            'data' => $categoryProduct->load(['company', 'user'])
        ], Response::HTTP_OK);
    }
}