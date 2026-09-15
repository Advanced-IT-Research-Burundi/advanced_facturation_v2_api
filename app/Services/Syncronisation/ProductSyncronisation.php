<?php

namespace App\Services\Syncronisation;

use App\Models\Libelle;
use App\Models\Product;
use App\Models\TruckSyncroniser;
use App\Services\SyncMainApp;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSyncronisation
{
    public function syncProducts(): array|string
    {
        $lastId = TruckSyncroniser::where('model_name', 'Product')
            ->latest('id')
            ->value('last_id') ?? 0;

        try {
            $response = (new SyncMainApp())->get('/products_sync/'.$lastId);
            $products = $response['data'] ?? [];

            if ($products === []) {
                return ['success' => true, 'total_synced' => 0];
            }

            DB::transaction(function () use ($products, &$maxId) {
                $maxId = collect($products)->max('id');

                foreach ($products as $product) {
                    $libelleId = null;
                    if (! empty($product['libelle']['name'])) {
                        $libelleId = Libelle::where('name', $product['libelle']['name'])
                            ->where('company_id', $product['libelle']['company_id'] ?? $product['company_id'])
                            ->value('id');
                    }

                    Product::updateOrCreate(
                        ['item_code' => $product['item_code']],
                        [
                            'item_designation' => $product['item_designation'],
                            'item_measurement_unit' => $product['item_measurement_unit'],
                            'barcode' => $product['barcode'] ?? null,
                            'vat_rate' => $product['vat_rate'] ?? 0,
                            'company_id' => $product['company_id'] ?? null,
                            'product_unit_id' => $product['product_unit_id'] ?? null,
                            'product_category_id' => $product['product_category_id'] ?? null,
                            'id_libelle' => $libelleId,
                            'user_id' => $product['user_id'],
                            'code_product' => $product['code_product'] ?? null,
                            'marque' => $product['marque'] ?? null,
                            'quantite' => $product['quantite'] ?? 0,
                            'quantite_alert' => $product['quantite_alert'] ?? 0,
                            'price' => $product['price'] ?? 0,
                            'price_promo' => $product['price_promo'] ?? null,
                            'price_ttc' => $product['price_ttc'] ?? null,
                            'price_max' => $product['price_max'] ?? null,
                            'price_min' => $product['price_min'] ?? null,
                            'price_tvac' => $product['price_tvac'] ?? null,
                            'item_ott_tax' => $product['item_ott_tax'] ?? null,
                            'item_tsce_tax' => $product['item_tsce_tax'] ?? null,
                            'date_expiration' => $product['date_expiration'] ?? null,
                            'image' => $product['image'] ?? null,
                            'type' => $product['type'] ?? null,
                            'description' => $product['description'] ?? null,
                            'is_production' => $product['is_production'] ?? false,
                            'created_at' => $product['created_at'] ?? now(),
                            'updated_at' => $product['updated_at'] ?? now(),
                        ]
                    );
                }

                TruckSyncroniser::create([
                    'model_name' => 'Product',
                    'last_id' => $maxId,
                ]);
            });

            return ['success' => true, 'total_synced' => count($products), 'last_id' => $maxId];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return $exception->getMessage();
        }
    }
}
