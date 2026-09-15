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

    public function syncInvoices(){
        $invoinceSyncronisation = new InvoinceSyncronisation();
        $invoinceSyncronisation->syncInvoices();
        
    }

    public function get($url,$params=null){
        $currntUrl =  self::BASE_URL . $url;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
        ])->get( $currntUrl,$params);

        if($response->successful()) {
            $response = $response->json();
            return $response;
        }
        return false;
    }

    private function finishTable(array &$summary, string $table, array $tableSummary): void
    {
        $summary['tables'][$table] = $tableSummary;

        foreach ($tableSummary as $key => $value) {
            $summary[$key] += $value;
        }
    }

    private function addConflict(array &$summary, string $table, string $pk, string $key, array $local, array $remote, ?string $winner): void
    {
        $summary['conflict_count']++;

        if (count($summary['conflicts']) < 500) {
            $summary['conflicts'][] = [
                'table' => $table,
                'primary_key' => $pk,
                'value' => $key,
                'local_updated_at' => $local['updated_at'] ?? null,
                'remote_updated_at' => $remote['updated_at'] ?? null,
                'winner' => $winner,
            ];
        }
    }

    private function saveProgress(string $table, $lastKey = null): void
    {
        Cache::put($this->progressKey(), ['table' => $table, 'last_key' => $lastKey], now()->addDays(7));
    }

    private function progress(): array
    {
        return Cache::get($this->progressKey(), ['table' => null, 'last_key' => null]);
    }

    private function progressKey(): string
    {
        return 'sync_main_app_progress_' . sha1(env('DB_DATABASE') . '|' . env('SYNC_DB_DATABASE'));
    }

    private function disableForeignKeys(): void
    {
        $this->schema('default')->disableForeignKeyConstraints();
        $this->schema(self::REMOTE_CONNECTION)->disableForeignKeyConstraints();
    }

    private function enableForeignKeys(): void
    {
        $this->schema('default')->enableForeignKeyConstraints();
        $this->schema(self::REMOTE_CONNECTION)->enableForeignKeyConstraints();
    }

    private function db(string $connection): ConnectionInterface
    {
        return $connection === 'default' ? DB::connection() : DB::connection($connection);
    }

    private function schema(string $connection)
    {
        return $connection === 'default' ? Schema::getFacadeRoot() : Schema::connection($connection);
    }

    private function dateValue($value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function looksLikeDate(string $key, string $value): bool
    {
        return str_ends_with($key, '_at') || str_ends_with($key, '_date') || preg_match('/^\d{4}-\d{2}-\d{2}/', $value);
    }

    private function summary(): array { return $this->tableSummary() + ['conflict_count' => 0, 'tables' => [], 'conflicts' => [], 'skipped_before_resume' => [], 'errors' => []]; }

    private function tableSummary(): array { return ['created_local' => 0, 'updated_local' => 0, 'created_remote' => 0, 'updated_remote' => 0, 'unchanged' => 0, 'skipped_conflicts' => 0]; }
}
