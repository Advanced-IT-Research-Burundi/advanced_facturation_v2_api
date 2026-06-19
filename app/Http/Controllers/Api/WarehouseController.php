<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserWarehouseResource;
use App\Http\Resources\WarehouseResource;
use App\Models\Product;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseController extends Controller
{
    public function mesStock()
    {
        $stoks = UserWarehouse::with(['warehouse'])->where('user_id', auth()->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => UserWarehouseResource::collection($stoks),
        ], Response::HTTP_OK);
    }

    public function product_in_stock($stock_id)
    {
        $perPage = max(1, min((int) request('per_page', 20), 100));
        $search = request('search') ?? '';
        // Sélectionner les produits qui existent dans le warehouse sélectionné
        $products = Product::with(['warehouseProducts' => function ($query) use ($stock_id) {
            $query->where('warehouse_id', $stock_id);
        }])
            ->whereHas('warehouseProducts', function ($query) use ($stock_id) {
                $query->where('warehouse_id', $stock_id);
            })
            ->when(! empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('item_designation', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage)
            ->through(function ($product) {
                $stock = $product->warehouseProducts->first();
                $stockQuantity = (float) ($stock?->quantity ?? 0);
                $alertThreshold = (float) ($product->quantite_alert ?? 0);

                return [
                    'id' => $product->id,
                    'item_code' => $product->item_code,
                    'item_designation' => $product->item_designation,
                    'item_measurement_unit' => $product->item_measurement_unit,
                    'quantite_alert' => $product->quantite_alert,
                    'stock_quantity' => $stockQuantity,
                    'alert_threshold' => $alertThreshold,
                    'is_alert' => $stockQuantity <= $alertThreshold,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->total(),
        ]);
    }

    public function product_not_stock($stock_id)
    {
        $perPage = max(1, min((int) request('per_page', 20), 100));
        $search = request('search') ?? '';
        // Sélectionner tous les produits qui n'existent pas dans le warehouse sélectionné
        $products = Product::whereDoesntHave('warehouseProducts', function ($query) use ($stock_id) {
            $query->where('warehouse_id', $stock_id);
        })
            ->when(! empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('item_designation', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->total(),
        ], Response::HTTP_OK);
    }

    public function addProduct($id, $product_id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $product = Product::findOrFail($product_id);

        // Vérifier si le produit est déjà dans l'entrepôt
        $existingEntry = WarehouseProduct::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Le produit est déjà dans l\'entrepôt.',
            ], Response::HTTP_CONFLICT);
        }

        // Ajouter le produit à l'entrepôt avec une quantité initiale de 0
        $warehouseProduct = WarehouseProduct::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'unit_price' => 0,
            'currency' => 'BIF',
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté à l\'entrepôt avec succès.',
            'data' => $warehouseProduct,
        ], Response::HTTP_CREATED);
    }

    public function removeProduct($id, $product_id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $product = Product::findOrFail($product_id);

        // Vérifier si le produit existe dans l'entrepôt
        $warehouseProduct = WarehouseProduct::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $warehouseProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Le produit n\'existe pas dans cet entrepôt.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si le stock est à zéro avant de supprimer
        if ($warehouseProduct->quantity > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un produit avec un stock non nul. Stock actuel: '.$warehouseProduct->quantity,
            ], Response::HTTP_CONFLICT);
        }

        // Supprimer le produit de l'entrepôt
        $warehouseProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit retiré de l\'entrepôt avec succès.',
        ], Response::HTTP_OK);
    }

    public function index()
    {
        $stocks = Warehouse::with(['company'])
            ->where('company_id', auth()->user()->company_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => WarehouseResource::collection($stocks),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $warehouse = Warehouse::with(['company'])
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $warehouse->load(['company']),
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_production' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = auth()->id();
        // company_id is set by HasCompanyId trait

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt créé',
            'data' => $warehouse->load('company'),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_production' => 'sometimes|boolean',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt mis à jour',
            'data' => $warehouse,
        ], Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $hasStock = WarehouseProduct::where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->exists();

        if ($hasStock) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un entrepôt contenant des produits avec du stock.',
            ], Response::HTTP_CONFLICT);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt supprimé',
        ], Response::HTTP_OK);
    }

    public function warehouseProducts($id)
    {
        $perPage = max(1, min((int) request('per_page', 20), 100));
        $warehouse = Warehouse::with('warehouseProducts')
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $warehouse->warehouseProducts()->paginate($perPage),
        ], Response::HTTP_OK);
    }

    public function warehouseNotProducts($id)
    {
        $perPage = max(1, min((int) request('per_page', 20), 100));
        $warehouse = Warehouse::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $assignedProductIds = $warehouse->warehouseProducts()->pluck('product_id')->toArray();

        $notAssignedProducts = Product::where('company_id', auth()->user()->company_id)
            ->whereNotIn('id', $assignedProductIds)
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notAssignedProducts,
        ], Response::HTTP_OK);
    }
}
