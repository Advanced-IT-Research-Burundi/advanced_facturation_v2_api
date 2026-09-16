<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Libelle;
use App\Models\Product;
use App\Models\User;
use App\Models\Company;

class AppSyncroniserController extends Controller
{

    public function syncCompanies($max_id){
        $companies = Company::where('id', '>', $max_id)->take(2)->get();
        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

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

    public function SyncWarehouseProduct($max_id){
        $warehouseProducts = \App\Models\WarehouseProduct::where('id', '>', $max_id)->take(100)->get();
        return response()->json([
            'success' => true,
            'data' => $warehouseProducts,
        ]);
    }

    public function syncLibelles($max_id){
        return response()->json([
            'success' => true,
            'data' => Libelle::where('id', '>', $max_id)->take(100)->get(),
        ]);
    }

    public function syncUsers($max_id){
        return response()->json([
            'success' => true,
            'data' => User::where('id', '>', $max_id)
                ->select(['id', 'name', 'email', 'company_id', 'user_id', 'is_server', 'server_code', 'created_at', 'updated_at'])
                ->take(100)
                ->get(),
        ]);
    }
}
