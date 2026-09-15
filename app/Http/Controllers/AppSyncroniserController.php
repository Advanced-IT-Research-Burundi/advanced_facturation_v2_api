<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Models\Warehouse;
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

    public function SyncWarehouseProduct($max_id){
        $warehouseProducts = \App\Models\WarehouseProduct::where('id', '>', $max_id)->take(100)->get();
        return response()->json([
            'success' => true,
            'data' => $warehouseProducts,
        ]);
    }
}
