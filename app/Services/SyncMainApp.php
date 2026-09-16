<?php

namespace App\Services;

use App\Services\Syncronisation\CompanySyncronisation;
use App\Services\Syncronisation\InvoinceSyncronisation;
use App\Services\Syncronisation\LibelleSyncronisation;
use App\Services\Syncronisation\ProductSyncronisation;
use App\Services\Syncronisation\StockSyncronisation;
use App\Services\Syncronisation\UserSyncronisation;
use App\Services\Syncronisation\WarehouseProductSyncronisation;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncMainApp
{
    private $BASE_URL;

    private static ?string $token = null;

    public function __construct()
    {
        $this->BASE_URL = env('APP_PARENT_URL', '');
    }

    public function getToken()
    {
        if (self::$token) {
            return self::$token;
        }

        $response = Http::post($this->BASE_URL.'/login', [
            'email' => 'nijeanlionel@gmail.com',
            'password' => 'Advanced2026',
        ]);
        if ($response->successful()) {
            $response = $response->json();

            return self::$token = $response['data']['access_token'] ?? null;
        }

        Log::warning('Synchronization login failed.', [
            'status' => $response->status(),
        ]);

        return false;
    }

    public function syncAll()
    {
        (new CompanySyncronisation)->syncCompanies();
        (new UserSyncronisation)->syncUsers();
        $stockSyncronisation = new StockSyncronisation;
        $stockSyncronisation->stockSync();
        (new WarehouseProductSyncronisation)->syncWarehouseProducts();
        (new LibelleSyncronisation)->syncLibelles();
        (new ProductSyncronisation)->syncProducts();

        (new InvoinceSyncronisation)->syncInvoices();
        $stockResult = $stockSyncronisation->syncStockMovements();

        Log::info('Stock movement synchronization completed.', [
            'result' => $stockResult,
        ]);

        return [
            'stock_movements' => $stockResult,
        ];
    }

    public function get($url, $params = null)
    {
        $currentUrl = $this->BASE_URL.$url;
        $token = $this->getToken();

        if (! $token) {
            Log::warning('Synchronization request skipped: authentication failed.', [
                'url' => $currentUrl,
            ]);

            return false;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer '.$token])
                ->get($currentUrl, $params);
        } catch (Exception $exception) {
            Log::error('Synchronization request failed: '.$exception->getMessage(), [
                'url' => $currentUrl,
            ]);

            return false;
        }

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('Synchronization endpoint returned an error.', [
            'url' => $currentUrl,
            'status' => $response->status(),
        ]);

        return false;
    }
}
