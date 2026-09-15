<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Product;
class AppSyncroniserController extends Controller
{

    public function syncWarehouses($max_id){
        $warehouses = Warehouse::where('id', '>', $max_id)->take(2)->get();
        return response()->json([
            'success' => true,
            'data' => $warehouses,
        ]);
    }

    //
    public function syncStockMouvements($max_id){
        $stockMovements = StockMovement::where('id', '>', $max_id)->take(100)->get();
        return response()->json([
            'success' => true,
            'data' => $stockMovements,
        ]);
    }

    public function syncProducts($max_id){
        $products = Product::with([
            'company','user','productUnit','categoryProduct','libelle'
            ])
        ->where('id', '>', $max_id)->take(100)->get();
        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}
