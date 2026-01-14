<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $query = Product::with(['company', 'productUnit', 'categoryProduct', 'user']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('item_code', 'like', "%{$search}%")
                  ->orWhere('item_designation', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($query->paginate(15))
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
           // 'item_measurement_unit' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'vat_rate' => 'required|numeric|min:0|max:100',
           // 'company_id' => 'required|exists:companies,id',
            'product_unit_id' => 'nullable|exists:product_units,id',
            'product_category_id' => 'nullable|exists:category_products,id',
            'code_product' => 'nullable|string|max:255',
            'marque' => 'nullable|string|max:255',
            'quantite' => 'nullable|numeric|min:0',
            'quantite_alert' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'price_ttc' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'price_min' => 'nullable|numeric|min:0',
            'price_tvac' => 'nullable|numeric|min:0',
            'item_ott_tax' => 'nullable|numeric|min:0',
            'item_tsce_tax' => 'nullable|numeric|min:0',
            'date_expiration' => 'nullable|date',
            'image' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = auth()->id();
        try {
            $product = Product::create(attributes: $validated);
        } catch (\Exception $e) {
            return response()->json([
                'success'=> false,
                'message'=> $e->getMessage()
            ]
            );

        }

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user']))
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user', 'stockMovements', 'warehouseProducts']))
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
            //'item_measurement_unit' => 'sometimes|required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'vat_rate' => 'sometimes|required|numeric|min:0|max:100',
            //'company_id' => 'sometimes|required|exists:companies,id',
            'product_unit_id' => 'nullable|exists:product_units,id',
            'product_category_id' => 'nullable|exists:category_products,id',
            'code_product' => 'nullable|string|max:255',
            'marque' => 'nullable|string|max:255',
            'quantite' => 'nullable|numeric|min:0',
            'quantite_alert' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'price_ttc' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'price_min' => 'nullable|numeric|min:0',
            'price_tvac' => 'nullable|numeric|min:0',
            'item_ott_tax' => 'nullable|numeric|min:0',
            'item_tsce_tax' => 'nullable|numeric|min:0',
            'date_expiration' => 'nullable|date',
            'image' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user']))
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
            'message' => 'Produit supprimé avec succès'
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
            'message' => 'Produit restauré avec succès',
            'data' => new ProductResource($product->load(['company', 'productUnit', 'categoryProduct', 'user']))
        ], Response::HTTP_OK);
    }
}
