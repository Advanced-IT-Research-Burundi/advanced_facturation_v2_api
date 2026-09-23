<?php

namespace App\Console\Commands;

use App\Models\AppConfig;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Services\ObrService;
use Illuminate\Console\Command;
use App\Services\SyncMainApp;

class ObrCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'obr:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $SyncMainApp = new SyncMainApp();
        // $token = $SyncMainApp->getToken();
        // dd($token);
        $this->initStockMouvement();
    }

    public function initStockMouvement(){

        $warehouses = WarehouseProduct::with(['product', 'warehouse'])->get();
        // display progress
        

        foreach ($warehouses as $key=> $warehouse) {    
            $this->info($warehouses->count() / ($key+1));
            StockMovement::create([
        'system_or_device_id'=> AppConfig::getConfigKey('OBR_USERNAME'),
        'item_code'=> $warehouse->product_id,
        'item_designation'=> $warehouse->product?->item_designation ?? "N/A",
        'item_quantity'=>$warehouse->quantity,
        'item_measurement_unit'=>$warehouse->product?->item_measurement_unit ?? "N/A",
        'item_purchase_or_sale_price'=>$warehouse->unit_price,
        'item_purchase_or_sale_currency'=>$warehouse->currency,
        'item_movement_type' => "EI",   
        'is_production' => 1,
        'item_movement_invoice_ref'=> "",
        'item_movement_description' => "Réapprovisionnement",
        'item_movement_date' => now()->format('Y-m-d H:i:s'),
        'obr_submission_status' => "PENDING",
        'obr_sent_at' => null,
        'company_id' => 1,
        'product_id' => $warehouse->product_id,
        'warehouse_id' => $warehouse->warehouse_id,
        'invoice_id' => null,
        'user_id' => 1,
        'created_by' => 1,
        'created_by_id' => 1,
            ]);
        }

    }
}
