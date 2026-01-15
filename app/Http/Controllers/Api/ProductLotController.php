<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductLotController extends Controller
{
    /**
     * Display a listing of product lots.
     */
    public function index(Request $request)
    {
        $query = ProductLot::with(['product', 'warehouse', 'fournisseur', 'company', 'user']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('lot_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($q) use ($search) {
                      $q->where('item_designation', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%");
                  });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('expiring_soon')) {
            $days = $request->integer('expiring_days', 90);
            $query->expiringSoon($days);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate(15),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created product lot.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'lot_number' => 'required|string|max:255',
            'manufacturing_date' => 'nullable|date',
            'expiration_date' => 'required|date|after:today',
            'initial_quantity' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'supplier_reference' => 'nullable|string|max:255',
            'fournisseur_id' => 'nullable|exists:fourinsseurs,id',
            'notes' => 'nullable|string',
        ]);

        // Verifier l'unicite du lot pour ce produit et entrepot
        $exists = ProductLot::where('product_id', $validated['product_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('lot_number', $validated['lot_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ce numero de lot existe deja pour ce produit dans cet entrepot',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated['current_quantity'] = $validated['initial_quantity'];
        $validated['status'] = 'active';
        $validated['user_id'] = auth()->id();

        try {
            $lot = ProductLot::create($validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lot cree avec succes',
            'data' => $lot->load(['product', 'warehouse', 'fournisseur']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified product lot.
     */
    public function show(ProductLot $productLot)
    {
        return response()->json([
            'success' => true,
            'data' => $productLot->load(['product', 'warehouse', 'fournisseur', 'company', 'user', 'patientHistories']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified product lot.
     */
    public function update(Request $request, ProductLot $productLot)
    {
        $validated = $request->validate([
            'lot_number' => 'sometimes|string|max:255',
            'manufacturing_date' => 'nullable|date',
            'expiration_date' => 'sometimes|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'supplier_reference' => 'nullable|string|max:255',
            'fournisseur_id' => 'nullable|exists:fourinsseurs,id',
            'status' => 'sometimes|in:active,expired,recalled,depleted',
            'notes' => 'nullable|string',
        ]);

        try {
            $productLot->update($validated);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lot mis a jour avec succes',
            'data' => $productLot->load(['product', 'warehouse', 'fournisseur']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified product lot.
     */
    public function destroy(ProductLot $productLot)
    {
        $productLot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lot supprime avec succes',
        ], Response::HTTP_OK);
    }

    /**
     * Get lots by product.
     */
    public function byProduct(Product $product)
    {
        $lots = ProductLot::with(['warehouse', 'fournisseur'])
            ->where('product_id', $product->id)
            ->orderBy('expiration_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lots,
        ], Response::HTTP_OK);
    }

    /**
     * Adjust lot quantity.
     */
    public function adjustQuantity(Request $request, ProductLot $lot)
    {
        $validated = $request->validate([
            'adjustment' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        $newQuantity = $lot->current_quantity + $validated['adjustment'];

        if ($newQuantity < 0) {
            return response()->json([
                'success' => false,
                'message' => 'La quantite ne peut pas etre negative',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lot->current_quantity = $newQuantity;
        $lot->notes = ($lot->notes ? $lot->notes . "\n" : '') .
            "[" . now()->format('Y-m-d H:i') . "] Ajustement: " .
            ($validated['adjustment'] > 0 ? '+' : '') . $validated['adjustment'] .
            " - Raison: " . $validated['reason'];

        if ($newQuantity <= 0) {
            $lot->status = 'depleted';
        } elseif ($lot->status === 'depleted') {
            $lot->status = 'active';
        }

        $lot->save();

        return response()->json([
            'success' => true,
            'message' => 'Quantite ajustee avec succes',
            'data' => $lot->load(['product', 'warehouse']),
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted lot.
     */
    public function restore($id)
    {
        $lot = ProductLot::withTrashed()->findOrFail($id);
        $lot->restore();

        return response()->json([
            'success' => true,
            'message' => 'Lot restaure avec succes',
            'data' => $lot->load(['product', 'warehouse']),
        ], Response::HTTP_OK);
    }
}
