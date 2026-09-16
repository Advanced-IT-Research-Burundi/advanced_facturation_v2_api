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
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncMainApp
{
    private $BASE_URL;

    private static ?string $token = null;

    private ?OutputStyle $output = null;

    public function __construct(?OutputStyle $output = null)
    {
        $this->BASE_URL = env('APP_PARENT_URL', '');
        $this->output = $output;
    }

    public function getToken()
    {
        if (self::$token) {
            return self::$token;
        }

        $this->log('Connexion au serveur parent...', 'comment');

        $response = Http::post($this->BASE_URL.'/login', [
            'email' => 'nijeanlionel@gmail.com',
            'password' => 'Advanced2026',
        ]);
        if ($response->successful()) {
            $response = $response->json();
            $this->log('Connexion réussie', 'info');

            return self::$token = $response['data']['access_token'] ?? null;
        }

        $this->log('Échec de connexion au serveur', 'error');
        Log::warning('Synchronization login failed.', [
            'status' => $response->status(),
        ]);

        return false;
    }

    public function syncAll(): array
    {
        $results = [];

        // 1. Companies
        $this->log('[1/8] Synchronisation des Companies...', 'comment');
        $result = (new CompanySyncronisation)->syncCompanies();
        $this->logResult('Companies', $result);
        $results['companies'] = $result;

        // 2. Users
        $this->log('[2/8] Synchronisation des Users...', 'comment');
        $result = (new UserSyncronisation)->syncUsers();
        $this->logResult('Users', $result);
        $results['users'] = $result;

        // 3. Warehouses
        $this->log('[3/8] Synchronisation des Warehouses...', 'comment');
        $stockSyncronisation = new StockSyncronisation;
        $result = $stockSyncronisation->stockSync();
        $this->logResult('Warehouses', $result);
        $results['warehouses'] = $result;

        // 4. Warehouse Products
        $this->log('[4/8] Synchronisation des Warehouse Products...', 'comment');
        $result = (new WarehouseProductSyncronisation)->syncWarehouseProducts();
        $this->logResult('Warehouse Products', $result);
        $results['warehouse_products'] = $result;

        // 5. Libelles
        $this->log('[5/8] Synchronisation des Libelles...', 'comment');
        $result = (new LibelleSyncronisation)->syncLibelles();
        $this->logResult('Libelles', $result);
        $results['libelles'] = $result;

        // 6. Products
        $this->log('[6/8] Synchronisation des Products...', 'comment');
        $result = (new ProductSyncronisation)->syncProducts();
        $this->logResult('Products', $result);
        $results['products'] = $result;

        // 7. Invoices
        $this->log('[7/8] Synchronisation des Invoices...', 'comment');
        $result = (new InvoinceSyncronisation)->syncInvoices();
        $this->logResult('Invoices', $result);
        $results['invoices'] = $result;

        // 8. Stock Movements
        $this->log('[8/8] Synchronisation des Stock Movements...', 'comment');
        $result = $stockSyncronisation->syncStockMovements();
        $this->logResult('Stock Movements', $result);
        $results['stock_movements'] = $result;

        Log::info('Synchronization completed.', ['results' => $results]);

        return $results;
    }

    private function log(string $message, string $type = 'line'): void
    {
        if ($this->output) {
            match ($type) {
                'info' => $this->output->writeln("<info>{$message}</info>"),
                'comment' => $this->output->writeln("<comment>{$message}</comment>"),
                'error' => $this->output->writeln("<error>{$message}</error>"),
                'success' => $this->output->writeln("<info>✓ {$message}</info>"),
                default => $this->output->writeln($message),
            };
        }
    }

    private function logResult(string $name, $result): void
    {
        if (is_array($result)) {
            $success = $result['success'] ?? false;
            $synced = $result['total_synced'] ?? $result['created'] ?? 0;

            if ($success) {
                $this->log("{$name}: {$synced} synchronisé(s)", 'success');
            } else {
                $this->log("{$name}: échec ou aucune donnée", 'error');
            }
        } elseif (is_string($result)) {
            $this->log("{$name}: erreur - {$result}", 'error');
        } else {
            $this->log("{$name}: terminé", 'success');
        }
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
