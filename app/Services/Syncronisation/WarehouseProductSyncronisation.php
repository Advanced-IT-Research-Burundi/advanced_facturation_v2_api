<?php

namespace App\Services\Syncronisation;

use App\Models\TruckSyncroniser;
use App\Models\WarehouseProduct;
use App\Services\SyncMainApp;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseProductSyncronisation
{
    public function syncWarehouseProducts(): array|string
    {
        $syncMainApp = new SyncMainApp();
        $lastId = TruckSyncroniser::where('model_name', 'WarehouseProduct')
            ->latest('id')
            ->value('last_id') ?? 0;

        try {
            $response = $syncMainApp->get('/warehouse_products_sync/'.$lastId);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return $exception->getMessage();
        }

        $warehouseProducts = $response['data'] ?? [];
        if (! is_array($warehouseProducts) || $warehouseProducts === []) {
            return [
                'success' => true,
                'total_synced' => 0,
            ];
        }

        DB::beginTransaction();

        try {
            $maxId = collect($warehouseProducts)->max('id');
            $synced = 0;

            foreach ($warehouseProducts as $warehouseProduct) {
                WarehouseProduct::updateOrCreate(
                    [
                        'product_id' => $warehouseProduct['product_id'],
                        'warehouse_id' => $warehouseProduct['warehouse_id'] ?? null,
                        'production_status' => $warehouseProduct['production_status'] ?? 'RAW',
                    ],
                    [
                        'company_id' => $warehouseProduct['company_id'] ?? null,
                        'quantity' => $warehouseProduct['quantity'],
                        'unit_price' => $warehouseProduct['unit_price'],
                        'price_promo' => $warehouseProduct['price_promo'] ?? null,
                        'currency' => $warehouseProduct['currency'] ?? null,
                        'last_stock_movement_id' => $warehouseProduct['last_stock_movement_id'] ?? null,
                        'user_id' => $warehouseProduct['user_id'],
                        'created_at' => $warehouseProduct['created_at'] ?? now(),
                        'updated_at' => $warehouseProduct['updated_at'] ?? now(),
                    ]
                );

                $synced++;
            }

            TruckSyncroniser::create([
                'model_name' => 'WarehouseProduct',
                'last_id' => $maxId,
            ]);

            DB::commit();

            return [
                'success' => true,
                'total_synced' => $synced,
                'last_id' => $maxId,
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());

            return $exception->getMessage();
        }
    }

    public function syncWarehouseProduct(): array|string
    {
        return $this->syncWarehouseProducts();
    }
}
