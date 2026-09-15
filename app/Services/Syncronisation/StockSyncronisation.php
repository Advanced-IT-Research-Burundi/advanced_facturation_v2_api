<?php

namespace App\Services\Syncronisation;

use App\Models\StockMovement;
use App\Models\Stock;
use App\Models\TruckSyncroniser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\SyncMainApp;
use Exception;

class StockSyncronisation{

    public function syncStockMovements(){
        try {
            $syncMainApp = new SyncMainApp();
            $maxId = TruckSyncroniser::where('model_name', 'StockMovement')->latest()->first()->last_id ?? 0;
            $stockMovements = $syncMainApp->get('/stock_movements_sync/' . $maxId);
            dump($stockMovements);
            exit;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    
    }

    
}
