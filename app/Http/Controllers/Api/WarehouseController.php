<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\UserWarehouseResource;
use App\Models\Product;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseController extends Controller
{
    public function mesStock(){
        $stoks = UserWarehouse::with(['warehouse'])->where('user_id', auth()->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => UserWarehouseResource::collection($stoks)
        ], Response::HTTP_OK);
    }
    public function product_in_stock($stock_id){

        $search = request("search") ?? "";
        // Sélectionner tous les produits qui n'existent pas dans le warehouse sélectionné
        $products = Product::whereHas('warehouseProducts', function($query) use ($stock_id) {
            $query->where('warehouse_id', $stock_id);
        })
        ->when(!empty($search), function($query) use ($search) {
            $query->where(function($q) use ($search) {
                $q->where('item_designation', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%");
            });
        })
        ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->count()
        ]);
    }

    public function product_not_stock($stock_id){
        $search = request("search") ?? "";
        // Sélectionner tous les produits qui n'existent pas dans le warehouse sélectionné
        $products = Product::whereDoesntHave('warehouseProducts', function($query) use ($stock_id) {
            $query->where('warehouse_id', $stock_id);
        })
        ->when(!empty($search), function($query) use ($search) {
            $query->where(function($q) use ($search) {
                $q->where('item_designation', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%");
            });
        })
        ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => count($products)
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
                'message' => 'Le produit est déjà dans l\'entrepôt.'
            ], Response::HTTP_CONFLICT);
        }

        // Ajouter le produit à l'entrepôt avec une quantité initiale de 0
        $warehouseProduct = WarehouseProduct::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'unit_price' => 0,
            'currency' => 'USD', // Vous pouvez ajuster la devise selon vos besoins
            'user_id' => auth()->user()?->id ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté à l\'entrepôt avec succès.',
            'data' => $warehouseProduct
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

        if (!$warehouseProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Le produit n\'existe pas dans cet entrepôt.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si le stock est à zéro avant de supprimer
        if ($warehouseProduct->quantity > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un produit avec un stock non nul. Stock actuel: ' . $warehouseProduct->quantity
            ], Response::HTTP_CONFLICT);
        }

        // Supprimer le produit de l'entrepôt
        $warehouseProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit retiré de l\'entrepôt avec succès.'
        ], Response::HTTP_OK);
    }

    public function index(){
        $stocks = Warehouse::with(['company'])
            ->where('company_id', auth()->user()->company_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' =>  WarehouseResource::collection($stocks)
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $warehouse = Warehouse::with(['company'])
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $warehouse->load(['company'])
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_production' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = auth()->id() ?? 1;
        // company_id is set by HasCompanyId trait

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt créé',
            'data' => $warehouse->load('company')
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt mis à jour',
            'data' => $warehouse
        ], Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt supprimé'
        ], Response::HTTP_OK);
    }

    public function warehouseProducts($id)
    {
        $warehouse = Warehouse::with('warehouseProducts')
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $warehouse->warehouseProducts
        ], Response::HTTP_OK);
    }

    public function warehouseNotProducts($id)
    {
        $warehouse = Warehouse::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $assignedProductIds = $warehouse->warehouseProducts()->pluck('product_id')->toArray();

        $notAssignedProducts = Product::where('company_id', auth()->user()->company_id)
            ->whereNotIn('id', $assignedProductIds)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notAssignedProducts
        ], Response::HTTP_OK);
    }
}
