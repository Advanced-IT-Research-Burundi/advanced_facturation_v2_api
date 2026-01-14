<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseController extends Controller
{
    public function product_in_stock($stock_id){
        $stocks = WarehouseProduct::with('product')->where("stock_id",$stock_id)->get();
        return response()->json($stocks);
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
    public function stocks(){
        $stocks = Warehouse::with(['company'])->get();
        
        return response()->json([
            'success' => true,
            'data' =>  WarehouseResource::collection($stocks)
        ], Response::HTTP_OK);
    }
    public function index(Request $request)
    {
        $query = Warehouse::with(['company']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(15)
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
          //  'company_id' => 'required|exists:companies,id',
        ]);

        $validated['user_id'] = auth()->id() ?? 1;

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entrepôt créé',
            'data' => $warehouse->load('company')
        ], Response::HTTP_CREATED);
    }
}