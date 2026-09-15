<?php

namespace App\Services\Syncronisation;

use App\Models\StockMovement;
use App\Models\Stock;
use App\Models\TruckSyncroniser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\SyncMainApp;
use Exception;
use App\Models\Product;
use App\Models\Warehouse;


class StockSyncronisation{

    public function syncStockMovements(){
        try {
            $syncMainApp = new SyncMainApp();
            $maxId = TruckSyncroniser::where('model_name', 'StockMovement')->latest()->first()->last_id ?? 0;
            $stockMovements = $syncMainApp->get('/stock_movements_sync/' . $maxId);
            DB::beginTransaction();
            if($stockMovements["data"]){
                $maxId = collect($stockMovements["data"])->max('id');
                foreach($stockMovements["data"] as $stockMovement){
                  $stock = StockMovement::firstOrCreate(
                    [
                        'parent_id' => $stockMovement['id'],
                    ],
                    [
                        "parent_id" => $stockMovement['id'],
                        'item_code' => $stockMovement['item_code'],
                        "system_or_device_id" => $stockMovement["system_or_device_id"],
                        'item_designation' => $stockMovement['item_designation'],
                        'item_quantity' => $stockMovement['item_quantity'],
                        'item_measurement_unit' => $stockMovement['item_measurement_unit'],
                        'item_purchase_or_sale_price' => $stockMovement['item_purchase_or_sale_price'],
                        'item_purchase_or_sale_currency' => $stockMovement['item_purchase_or_sale_currency'],
                        'item_movement_type' => $stockMovement['item_movement_type'],
                        'item_movement_invoice_ref' => $stockMovement['item_movement_invoice_ref'],
                        'item_movement_date' => $stockMovement['item_movement_date'],
                        'obr_submission_status' => $stockMovement['obr_submission_status'],
                        'company_id' => $stockMovement['company_id'],
                        "invoice_id"=>$stockMovement["invoice_id"] ?? "",
                        'product_id' => $stockMovement['product_id'],
                        'warehouse_id' => $stockMovement['warehouse_id'],
                        'created_by' => $stockMovement['created_by'],
                        'user_id' => $stockMovement['user_id'],
                    ]);
                    dump(  $stock->id); 
                }
                TruckSyncroniser::create([
                    'model_name' => 'StockMovement',
                    'last_id' => $maxId,
                ]);
            }
            DB::commit();
        
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            return $e->getMessage();
        }
    
    }

    public function stockSync(){

        $syncMainApp = new SyncMainApp();
        $maxId = TruckSyncroniser::where('model_name', 'Warehouse')->latest()->first()->last_id ?? 0;
        $stocks = $syncMainApp->get('/warehouses_sync/' . $maxId);

        DB::beginTransaction();
        if($stocks["data"]){
            $maxId = collect($stocks["data"])->max('id');
            foreach($stocks["data"] as $stock){
                $stock = Warehouse::firstOrCreate(
                [
                    'parent_id' => $stock['id'],
                ],
                [
                    'name' => $stock['name'],
                    'location' => $stock['location'],
                    'description' => $stock['description'],
                    'is_production' => $stock['is_production'],
                    'parent_id' => $stock['parent_id'],
                    'company_id' => $stock['company_id'],
                    'user_id' => $stock['user_id'],
                ]);
            
                dump( " Stock : ", $stock->id); 
            }
            TruckSyncroniser::create([
                'model_name' => 'Stock',
                'last_id' => $maxId,
            ]);
        }
        DB::commit();
    }

    
}
