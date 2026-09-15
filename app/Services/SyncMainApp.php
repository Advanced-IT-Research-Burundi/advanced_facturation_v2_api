<?php

namespace App\Services;
use App\Models\Customer;
use Exception;
use Illuminate\Support\Facades\Http;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TruckSyncroniser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Syncronisation\InvoinceSyncronisation;
use App\Services\Syncronisation\StockSyncronisation;
use App\Services\Syncronisation\WarehouseProductSyncronisation;
use App\Services\Syncronisation\LibelleSyncronisation;
use App\Services\Syncronisation\ProductSyncronisation;
use App\Services\Syncronisation\UserSyncronisation;


class SyncMainApp{
    private const BASE_URL = 'http://127.0.0.1:8080/api';
    public function getToken(){
        $response = Http::post( self::BASE_URL . '/login', [
            'email' => 'nijeanlionel@gmail.com',
            'password' => 'Advanced2026'
        ]);
        if($response->successful()) {
            $response =  $response->json();
            return $response['data']['access_token'];
        }
        return false;
    }

    public function syncAll(){
        (new UserSyncronisation())->syncUsers();
        (new LibelleSyncronisation())->syncLibelles();
        (new ProductSyncronisation())->syncProducts();

        $stockSyncronisation = new StockSyncronisation();
        $stockSyncronisation->stockSync();
        (new WarehouseProductSyncronisation())->syncWarehouseProducts();

        (new InvoinceSyncronisation())->syncInvoices();
        $stockSyncronisation->syncStockMovements();
    }

    public function get($url,$params=null){
        $currentUrl = self::BASE_URL . $url;
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

        if($response->successful()) {
            return $response->json();
        }

        Log::warning('Synchronization endpoint returned an error.', [
            'url' => $currentUrl,
            'status' => $response->status(),
        ]);

        return false;
    }







}
