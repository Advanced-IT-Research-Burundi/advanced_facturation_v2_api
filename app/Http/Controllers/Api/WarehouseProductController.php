<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseProductController extends Controller
{
    /**
     * Display a listing of warehouse products
     * GET /api/warehouse-products
     */
    public function index(Request $request)
    {
        try {
            // Initialisation avec les relations
            $query = WarehouseProduct::with(['product', 'warehouse', 'user', 'lastStockMovement']);

            // RECHERCHE : Utilisation des colonnes réelles de votre table 'products'
            if ($request->filled('search')) {
                $search = $request->search;

                $query->whereHas('product', function ($q) use ($search) {
                    $q->where(function ($inner) use ($search) {
                        $inner->where('item_designation', 'like', "%{$search}%")
                            ->orWhere('item_code', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
                });
            }

            // FILTRE PAR WAREHOUSE
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // FILTRE DE STOCK
            if ($request->filled('filter') && $request->filter !== 'TOUT') {
                if ($request->filter === 'STOCK VIDE') {
                    $query->where('quantity', '<=', 0);
                } elseif ($request->filter === 'STOCK NON VIDE') {
                    $query->where('quantity', '>', 0);
                }
            }

            // Pagination
            $results = $query->latest()->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $results,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des stocks',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created warehouse product
     * POST /api/warehouse-products
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'quantity' => 'required|numeric|min:0',
                'unit_price' => 'required|numeric|min:0',
                'currency' => 'required|string|max:10',
                'user_id' => 'required|integer|exists:users,id',
            ]);

            // Vérifier l'unicité product_id + warehouse_id
            $existing = WarehouseProduct::where('product_id', $validated['product_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce produit existe déjà dans cet entrepôt',
                ], Response::HTTP_CONFLICT);
            }

            // Récupérer le produit pour diminuer sa quantité
            $product = \App\Models\Product::findOrFail($validated['product_id']);

            // Vérifier que la quantité disponible est suffisante
            if ($product->quantite < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Quantité insuffisante. Disponible: {$product->quantite}, Demandée: {$validated['quantity']}",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Diminuer la quantité du produit
            $product->decrement('quantite', $validated['quantity']);

            $warehouseProduct = WarehouseProduct::create($validated);

            return response()->json([
                'success' => true,
                'data' => $warehouseProduct->load(['product', 'warehouse', 'user', 'lastStockMovement']),
                'message' => 'Stock de produit créé avec succès',
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du stock',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified warehouse product
     * GET /api/warehouse-products/{id}
     */
    public function show($id)
    {
        try {
            $warehouseProduct = WarehouseProduct::with(['product', 'warehouse', 'user', 'lastStockMovement'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $warehouseProduct,
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock de produit non trouvé',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du stock',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified warehouse product
     * PUT/PATCH /api/warehouse-products/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $warehouseProduct = WarehouseProduct::findOrFail($id);

            $validated = $request->validate([
                'product_id' => 'sometimes|integer|exists:products,id',
                'warehouse_id' => 'sometimes|nullable|integer|exists:warehouses,id',
                'quantity' => 'sometimes|numeric|min:0',
                'unit_price' => 'sometimes|numeric|min:0',
                'currency' => 'sometimes|string|max:10',
                'user_id' => 'sometimes|integer|exists:users,id',
                'last_stock_movement_id' => 'sometimes|nullable|integer|exists:stock_movements,id',
            ]);

            // Vérifier l'unicité si product_id ou warehouse_id sont modifiés
            if ($request->filled('product_id') || $request->filled('warehouse_id')) {
                $productId = $validated['product_id'] ?? $warehouseProduct->product_id;
                $warehouseId = $validated['warehouse_id'] ?? $warehouseProduct->warehouse_id;

                $existing = WarehouseProduct::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ce produit existe déjà dans cet entrepôt',
                    ], Response::HTTP_CONFLICT);
                }
            }

            // Si la quantité change, ajuster la quantité du produit
            if ($request->filled('quantity')) {
                $product = \App\Models\Product::findOrFail($warehouseProduct->product_id);
                $oldQuantity = $warehouseProduct->quantity;
                $newQuantity = $validated['quantity'];
                $difference = $newQuantity - $oldQuantity;

                if ($difference > 0) {
                    // Augmentation: vérifier la disponibilité et diminuer le stock du produit
                    if ($product->quantite < $difference) {
                        return response()->json([
                            'success' => false,
                            'message' => "Quantité insuffisante. Disponible: {$product->quantite}, Nécessaire: {$difference}",
                        ], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                    $product->decrement('quantite', $difference);
                } else {
                    // Diminution: augmenter le stock du produit
                    $product->increment('quantite', abs($difference));
                }
            }

            $warehouseProduct->update($validated);

            return response()->json([
                'success' => true,
                'data' => $warehouseProduct->load(['product', 'warehouse', 'user', 'lastStockMovement']),
                'message' => 'Stock de produit mis à jour avec succès',
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock de produit non trouvé',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du stock',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete (soft delete) the specified warehouse product
     * DELETE /api/warehouse-products/{id}
     */
    public function destroy($id)
    {
        try {
            $warehouseProduct = WarehouseProduct::findOrFail($id);

            // Restaurer la quantité du produit avant la suppression
            $product = \App\Models\Product::findOrFail($warehouseProduct->product_id);
            $product->increment('quantite', $warehouseProduct->quantity);

            $warehouseProduct->delete();

            return response()->json([
                'success' => true,
                'message' => 'Stock de produit supprimé avec succès',
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock de produit non trouvé',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du stock',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted warehouse product
     * POST /api/warehouse-products/{id}/restore
     */
    public function restore($id)
    {
        try {
            $warehouseProduct = WarehouseProduct::withTrashed()->findOrFail($id);

            // Vérifier que le produit a suffisamment de quantité disponible
            $product = \App\Models\Product::findOrFail($warehouseProduct->product_id);
            if ($product->quantite < $warehouseProduct->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Quantité insuffisante pour restaurer. Disponible: {$product->quantite}, Nécessaire: {$warehouseProduct->quantity}",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Diminuer la quantité du produit
            $product->decrement('quantite', $warehouseProduct->quantity);

            $warehouseProduct->restore();

            return response()->json([
                'success' => true,
                'data' => $warehouseProduct->load(['product', 'warehouse', 'user', 'lastStockMovement']),
                'message' => 'Stock de produit restauré avec succès',
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock de produit non trouvé',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la restauration du stock',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
