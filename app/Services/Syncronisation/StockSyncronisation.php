<?php

namespace App\Services\Syncronisation;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TruckSyncroniser;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SyncMainApp;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockSyncronisation
{
    public function syncStockMovements()
    {
        try {
            $syncMainApp = new SyncMainApp;
            $maxId = TruckSyncroniser::where('model_name', 'StockMovement')->latest()->first()->last_id ?? 0;
            $stockMovements = $syncMainApp->get('/stock_movements_sync/'.$maxId);

            if (! is_array($stockMovements)) {
                Log::warning('Stock movement synchronization skipped: source endpoint returned no data.');

                return [
                    'success' => false,
                    'total_synced' => 0,
                ];
            }

            $received = is_array($stockMovements['data'] ?? null)
                ? count($stockMovements['data'])
                : 0;
            $created = 0;
            $existing = 0;
            $localUserId = User::query()->orderBy('id')->value('id');

            if (! $localUserId) {
                throw new Exception('Aucun utilisateur local disponible pour synchroniser les mouvements de stock.');
            }

            $resolveUserId = static function ($remoteUserId) use ($localUserId): int {
                return $remoteUserId && User::whereKey($remoteUserId)->exists()
                    ? (int) $remoteUserId
                    : (int) $localUserId;
            };

            DB::beginTransaction();
            if (! empty($stockMovements['data']) && is_array($stockMovements['data'])) {
                $maxId = collect($stockMovements['data'])->max('id');
                foreach ($stockMovements['data'] as $stockMovement) {
                    $attributes = [
                        'parent_id' => $stockMovement['id'],
                        'item_code' => $stockMovement['item_code'],
                        'system_or_device_id' => $stockMovement['system_or_device_id'],
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
                        'invoice_id' => $stockMovement['invoice_id'] ?? null,
                        'product_id' => $stockMovement['product_id'],
                        'warehouse_id' => $stockMovement['warehouse_id'],
                        'created_by' => $resolveUserId($stockMovement['created_by'] ?? null),
                        'user_id' => $resolveUserId($stockMovement['user_id'] ?? null),
                    ];

                    try {
                        $stock = StockMovement::firstOrCreate(
                            ['parent_id' => $stockMovement['id']],
                            $attributes
                        );
                    } catch (QueryException $exception) {
                        $this->refetchForeignKeyData($exception);

                        $stock = StockMovement::firstOrCreate(
                            ['parent_id' => $stockMovement['id']],
                            $attributes
                        );
                    }
                    $stock->wasRecentlyCreated ? $created++ : $existing++;
                }
                TruckSyncroniser::create([
                    'model_name' => 'StockMovement',
                    'last_id' => $maxId,
                ]);
            }
            DB::commit();

            return [
                'success' => true,
                'received' => $received,
                'created' => $created,
                'already_present' => $existing,
                'last_id' => $received > 0 ? $maxId : null,
            ];

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Stock movement synchronization failed: '.$e->getMessage());

            return $e->getMessage();
        }

    }

    private function refetchForeignKeyData(QueryException $exception): void
    {
        $message = $exception->getMessage();

        Log::warning('Foreign key missing during stock movement synchronization; refetching dependency.', [
            'error' => $message,
        ]);

        if (str_contains($message, 'created_by') || str_contains($message, 'user_id')) {
            (new UserSyncronisation)->syncUsers();

            return;
        }

        if (str_contains($message, 'product_id')) {
            (new ProductSyncronisation)->syncProducts();

            return;
        }

        if (str_contains($message, 'warehouse_id')) {
            $this->stockSync();

            return;
        }

        if (str_contains($message, 'invoice_id')) {
            (new InvoinceSyncronisation)->syncInvoices();
        }
    }

    public function stockSync()
    {

        $syncMainApp = new SyncMainApp;
        $maxId = TruckSyncroniser::where('model_name', 'Warehouse')->latest()->first()->last_id ?? 0;
        $stocks = $syncMainApp->get('/warehouses_sync/'.$maxId);

        if (! is_array($stocks)) {
            Log::warning('Warehouse synchronization skipped: source endpoint returned no data.');

            return [
                'success' => false,
                'total_synced' => 0,
            ];
        }

        $localUserId = User::query()->orderBy('id')->value('id');

        $resolveCompanyId = static function ($remoteCompanyId): ?int {
            return $remoteCompanyId && Company::whereKey($remoteCompanyId)->exists()
                ? (int) $remoteCompanyId
                : null;
        };

        $resolveUserId = static function ($remoteUserId) use ($localUserId): ?int {
            if (! $remoteUserId) {
                return $localUserId;
            }

            return User::whereKey($remoteUserId)->exists()
                ? (int) $remoteUserId
                : $localUserId;
        };

        DB::beginTransaction();
        if (! empty($stocks['data']) && is_array($stocks['data'])) {
            $maxId = collect($stocks['data'])->max('id');
            foreach ($stocks['data'] as $stock) {
                $companyId = $resolveCompanyId($stock['company_id'] ?? null);

                if ($companyId === null) {
                    Log::warning('Warehouse synchronization skipped for warehouse: company does not exist locally.', [
                        'warehouse_id' => $stock['id'],
                        'company_id' => $stock['company_id'] ?? null,
                    ]);

                    continue;
                }

                Warehouse::firstOrCreate(
                    [
                        'parent_id' => $stock['id'],
                    ],
                    [
                        'name' => $stock['name'],
                        'location' => $stock['location'],
                        'parent_id' => $stock['id'],
                        'is_production' => $stock['is_production'] ?? false,
                        'company_id' => $companyId,
                        'user_id' => $resolveUserId($stock['user_id'] ?? null),
                    ]
                );
            }
            TruckSyncroniser::create([
                'model_name' => 'Warehouse',
                'last_id' => $maxId,
            ]);
        }
        DB::commit();

        return [
            'success' => true,
            'total_synced' => count($stocks['data'] ?? []),
        ];
    }

    public function syncProducts()
    {
        $syncMainApp = new SyncMainApp;
        $maxId = TruckSyncroniser::where('model_name', 'Product')->latest()->first()->last_id ?? 0;
        $products = $syncMainApp->get('/products_sync/'.$maxId);
        dd($products);
        DB::beginTransaction();
        if ($products['data']) {
            $maxId = collect($products['data'])->max('id');
            foreach ($products['data'] as $product) {
                $p = Product::updateOrCreate(
                    [
                        'parent_id' => $product['id'],
                    ],
                    [

                    ]);

                dump(' Product : ', $product->p);
            }
            TruckSyncroniser::create([
                'model_name' => 'Product',
                'last_id' => $maxId,
            ]);
        }
        DB::commit();
    }
}
