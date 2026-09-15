<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockMovement;
class AppSyncroniserController extends Controller
{
    //
    public function syncStockMouvements($max_id){
        $stockMovements = StockMovement::where('id', '>', $max_id)->take(100)->get();
        return response()->json([
            'success' => true,
            'data' => $stockMovements,
        ]);
    }
}
