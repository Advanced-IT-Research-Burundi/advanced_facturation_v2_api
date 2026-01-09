<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockMovementsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // 1. Seed Stock Movements first
        $stockMovements = [];
        for ($i = 1; $i <= 20; $i++) {
            $companyId = $i <= 5 ? 1 : ($i <= 10 ? 2 : ($i <= 15 ? 3 : 4)); // Ensure company exists (1-10)
            $productId = $i <= 10 ? $i : ($i - 10);
            $warehouseId = $i <= 5 ? 1 : ($i <= 10 ? 2 : 3);
            
            $stockMovements[] = [
                'system_or_device_id' => 'DEV00' . $i,
                'item_code' => 'ITEM' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'item_designation' => 'Product ' . $i,
                'item_quantity' => rand(10, 100),
                'item_measurement_unit' => 'Unit',
                'item_purchase_or_sale_price' => rand(100, 1000),
                'item_purchase_or_sale_currency' => 'CDF',
                'item_movement_type' => 'IN',
                'item_movement_date' => now(),
                'obr_submission_status' => 'ACCEPTED',
                'company_id' => $companyId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'user_id' => 1,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('stock_movements')->insert($stockMovements);

        // 2. Seed Warehouse Products using the stock movement IDs
        $warehouseProducts = [];
        
        for ($i = 1; $i <= 20; $i++) {
            $productId = $i <= 10 ? $i : ($i - 10);
            $warehouseId = $i <= 5 ? 1 : ($i <= 10 ? 2 : 3);
            $companyId = $i <= 5 ? 1 : ($i <= 10 ? 2 : 3);
            
            $warehouseProducts[] = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => rand(10, 1000),
                'unit_price' => rand(500, 50000),
                'currency' => $i % 2 == 0 ? 'USD' : 'CDF',
                'last_stock_movement_id' => $i, // References the stock movement created above
                'user_id' => 1,
                'last_stock_movement_id_id' => $i, // Seems redundant but present in model/migration? Migration calls it last_stock_movement_id (FK). WarehouseProduct has it. Wait, WarehouseProduct Migration checks.
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        // Migration for warehouse_products (Step 6/152) likely has last_stock_movement_id.
        // Step 67 model had last_stock_movement_id_id which looked like a typo in fillable, but migration defines columns.
        // Step 152 was error log showing stock_movements FK.

        DB::table('warehouse_products')->insert($warehouseProducts);
    }
}
