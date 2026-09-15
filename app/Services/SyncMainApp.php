<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncMainApp
{
    private const REMOTE_CONNECTION = 'sync_main_app';

    public function syncInvoices(): array
    {
        return $this->syncAllTables();
    }

    public function syncAllTables(?array $tables = null): array
    {
        $summary = $this->summary();
        $completed = false;

        try {
            if (! $this->configureRemoteConnection()) {
                $summary['errors'][] = 'Configure SYNC_DB_DATABASE pour connecter la base du deuxieme backend.';

                return $summary;
            }

            $tables = $tables ?: $this->syncableTables();
            $progress = $this->progress();
            $started = empty($progress['table']);
            $this->disableForeignKeys();

            try {
                foreach ($tables as $table) {
                    if (! $started && $table !== $progress['table']) {
                        $summary['skipped_before_resume'][] = $table;
                        continue;
                    }

                    $started = true;
                    $this->syncTable($table, $summary, $progress['table'] === $table ? $progress['last_key'] : null);
                    $this->saveProgress($table);
                    $progress = ['table' => null, 'last_key' => null];
                }

                $completed = true;
            } finally {
                $this->enableForeignKeys();
            }
        } catch (Throwable $e) {
            $summary['errors'][] = $e->getMessage();
            Log::error('Erreur de synchronisation bidirectionnelle.', ['exception' => $e]);
        }

        if ($completed) {
            Cache::forget($this->progressKey());
        }

        return $summary;
    }

    private function syncTable(string $table, array &$summary, $afterKey = null): void
    {
        $pk = $this->primaryKey($table);

        if (! $pk) {
            $this->syncTableWithoutPrimaryKey($table, $summary);
            return;
        }

        $keys = $this->tableKeys($table, $pk, $afterKey);
        $tableSummary = $this->tableSummary();

        foreach ($keys as $key) {
            $local = $this->row('default', $table, $pk, $key);
            $remote = $this->row(self::REMOTE_CONNECTION, $table, $pk, $key);

            if (! $local) {
                $this->upsertRow('default', $table, $pk, $remote);
                $tableSummary['created_local']++;
            } elseif (! $remote) {
                $this->upsertRow(self::REMOTE_CONNECTION, $table, $pk, $local);
                $tableSummary['created_remote']++;
            } elseif ($this->rowHash($local) === $this->rowHash($remote)) {
                $tableSummary['unchanged']++;
            } else {
                $this->resolveConflict($table, $pk, (string) $key, $local, $remote, $tableSummary, $summary);
            }

            $this->saveProgress($table, $key);
        }

        $this->finishTable($summary, $table, $tableSummary);
    }

    private function syncTableWithoutPrimaryKey(string $table, array &$summary): void
    {
        $tableSummary = $this->tableSummary();
        $local = $this->rowsByHash('default', $table);
        $remote = $this->rowsByHash(self::REMOTE_CONNECTION, $table);

        foreach (array_diff_key($remote, $local) as $row) {
            $this->db('default')->table($table)->insert($this->filterColumns('default', $table, $row));
            $tableSummary['created_local']++;
        }

        foreach (array_diff_key($local, $remote) as $row) {
            $this->db(self::REMOTE_CONNECTION)->table($table)->insert($this->filterColumns(self::REMOTE_CONNECTION, $table, $row));
            $tableSummary['created_remote']++;
        }

        $tableSummary['unchanged'] = count(array_intersect_key($local, $remote));
        $this->finishTable($summary, $table, $tableSummary);
    }

    private function resolveConflict(string $table, string $pk, string $key, array $local, array $remote, array &$tableSummary, array &$summary): void
    {
        $winner = $this->winner($local, $remote);
        $this->addConflict($summary, $table, $pk, $key, $local, $remote, $winner);

        if ($winner === 'local') {
            $this->upsertRow(self::REMOTE_CONNECTION, $table, $pk, $local);
            $tableSummary['updated_remote']++;
            return;
        }

        if ($winner === 'remote') {
            $this->upsertRow('default', $table, $pk, $remote);
            $tableSummary['updated_local']++;
            return;
        }

        $tableSummary['skipped_conflicts']++;
    }

    private function tableKeys(string $table, string $pk, $afterKey): array
    {
        $local = $this->keys('default', $table, $pk, $afterKey);
        $remote = $this->keys(self::REMOTE_CONNECTION, $table, $pk, $afterKey);
        $keys = array_values(array_unique(array_merge($local, $remote)));

        usort($keys, fn ($a, $b) => is_numeric($a) && is_numeric($b) ? $a <=> $b : strcmp((string) $a, (string) $b));

        return $keys;
    }

    private function keys(string $connection, string $table, string $pk, $afterKey): array
    {
        return $this->db($connection)
            ->table($table)
            ->when($afterKey !== null, fn ($query) => $query->where($pk, '>', $afterKey))
            ->orderBy($pk)
            ->pluck($pk)
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    private function row(string $connection, string $table, string $pk, $key): ?array
    {
        $row = $this->db($connection)->table($table)->where($pk, $key)->first();

        return $row ? (array) $row : null;
    }

    private function upsertRow(string $connection, string $table, string $pk, array $row): void
    {
        $db = $this->db($connection);
        $data = $this->filterColumns($connection, $table, $row);
        $key = $data[$pk] ?? null;

        if ($key === null) {
            return;
        }

        if (! $db->table($table)->where($pk, $key)->exists()) {
            $db->table($table)->insert($data);
            return;
        }

        $update = array_diff_key($data, [$pk => true]);

        if ($update) {
            $db->table($table)->where($pk, $key)->update($update);
        }
    }

    private function rowsByHash(string $connection, string $table): array
    {
        return $this->db($connection)
            ->table($table)
            ->get()
            ->mapWithKeys(fn ($row) => [$this->rowHash((array) $row) => (array) $row])
            ->all();
    }

    private function syncableTables(): array
    {
        $remote = $this->tableNames(self::REMOTE_CONNECTION);

        return array_values(array_filter($this->tableNames('default'), fn ($table) => in_array($table, $remote, true)));
    }

    private function tableNames(string $connection): array
    {
        $rows = $this->db($connection)->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        return array_map(fn ($row) => array_values((array) $row)[0], $rows);
    }

    private function primaryKey(string $table): ?string
    {
        $localPk = $this->primaryKeyFor('default', $table);
        $remotePk = $this->primaryKeyFor(self::REMOTE_CONNECTION, $table);

        return $localPk && $localPk === $remotePk ? $localPk : null;
    }

    private function primaryKeyFor(string $connection, string $table): ?string
    {
        $rows = $this->db($connection)->select('SHOW KEYS FROM ' . $this->quote($table) . " WHERE Key_name = 'PRIMARY'");
        $columns = array_map(fn ($row) => $row->Column_name, $rows);

        return count($columns) === 1 ? $columns[0] : null;
    }

    private function rowHash(array $row): string
    {
        unset($row['created_at'], $row['updated_at']);
        ksort($row);

        return sha1(json_encode($this->canonicalize($row)));
    }

    private function canonicalize(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_numeric($value)) {
                $row[$key] = number_format((float) $value, 6, '.', '');
            } elseif (is_string($value) && $this->looksLikeDate($key, $value)) {
                $row[$key] = $this->dateValue($value)?->toDateTimeString() ?? $value;
            } elseif (is_string($value) && in_array(substr(trim($value), 0, 1), ['[', '{'], true)) {
                $decoded = json_decode($value, true);
                $row[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }
        }

        return $row;
    }

    private function winner(array $local, array $remote): ?string
    {
        $localDate = $this->dateValue($local['updated_at'] ?? $local['created_at'] ?? null);
        $remoteDate = $this->dateValue($remote['updated_at'] ?? $remote['created_at'] ?? null);

        if ($localDate && $remoteDate && ! $localDate->eq($remoteDate)) {
            return $localDate->gt($remoteDate) ? 'local' : 'remote';
        }

        if ($localDate xor $remoteDate) {
            return $localDate ? 'local' : 'remote';
        }

        return in_array(env('SYNC_DEFAULT_WINNER', 'local'), ['local', 'remote'], true)
            ? env('SYNC_DEFAULT_WINNER', 'local')
            : null;
    }

    private function configureRemoteConnection(): bool
    {
        $database = env('SYNC_DB_DATABASE');

        if (! $database) {
            return false;
        }

        $connection = array_merge(config('database.connections.' . config('database.default')), [
            'host' => env('SYNC_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('SYNC_DB_PORT', env('DB_PORT', '3306')),
            'database' => $database,
            'username' => env('SYNC_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('SYNC_DB_PASSWORD', env('DB_PASSWORD', '')),
        ]);

        if ($this->isDefaultConnection($connection)) {
            return false;
        }

        config(['database.connections.' . self::REMOTE_CONNECTION => $connection]);
        DB::purge(self::REMOTE_CONNECTION);

        return true;
    }

    private function isDefaultConnection(array $remote): bool
    {
        return (string) $remote['host'] === (string) env('DB_HOST', '127.0.0.1')
            && (string) $remote['port'] === (string) env('DB_PORT', '3306')
            && (string) $remote['database'] === (string) env('DB_DATABASE')
            && (string) $remote['username'] === (string) env('DB_USERNAME');
    }

    private function filterColumns(string $connection, string $table, array $row): array
    {
        return array_intersect_key($row, array_flip($this->schema($connection)->getColumnListing($table)));
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
