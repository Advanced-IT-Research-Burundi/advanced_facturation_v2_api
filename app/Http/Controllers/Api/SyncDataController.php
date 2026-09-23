<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

class SyncDataController extends Controller
{
    private const LOCK_KEY = 'app-sync-data:running';

    private const LAST_RUN_KEY = 'app-sync-data:last-run';

    /**
     * Dernière exécution de la synchronisation (logs + statut)
     */
    public function last(Request $request)
    {
        if ($denied = $this->denyIfNotAdmin($request)) {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'message' => 'Dernière synchronisation',
            'data' => [
                'running' => Cache::has(self::LOCK_KEY),
                'last_run' => Cache::get(self::LAST_RUN_KEY),
            ],
        ]);
    }

    /**
     * Lance `php artisan app:sync-data` et diffuse les logs en direct (NDJSON).
     *
     * La commande tourne dans un processus séparé : dans une requête HTTP authentifiée,
     * le CompanyScope et le trait HasCompanyId modifieraient les données synchronisées.
     */
    public function run(Request $request)
    {
        if ($denied = $this->denyIfNotAdmin($request)) {
            return $denied;
        }

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Une synchronisation est déjà en cours.',
            ], Response::HTTP_CONFLICT);
        }

        $user = $request->user();

        return new StreamedResponse(function () use ($lock, $user) {
            set_time_limit(0);
            ignore_user_abort(true);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $logs = [];
            $startedAt = now();
            $emit = function (string $level, string $message) use (&$logs) {
                $entry = ['level' => $level, 'message' => $message, 'time' => now()->format('H:i:s')];
                $logs[] = $entry;
                echo json_encode($entry, JSON_UNESCAPED_UNICODE)."\n";
                flush();
            };

            $status = 'failed';
            try {
                $emit('comment', '$ php artisan app:sync-data');

                $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                $buffer = '';
                $result = Process::forever()
                    ->path(base_path())
                    ->run([$php, 'artisan', 'app:sync-data', '--no-ansi', '--no-interaction'], function (string $type, string $output) use (&$buffer, $emit) {
                        $buffer .= $output;
                        while (($pos = strpos($buffer, "\n")) !== false) {
                            $line = rtrim(substr($buffer, 0, $pos), "\r");
                            $buffer = substr($buffer, $pos + 1);
                            if (trim($line) !== '') {
                                $emit($type === 'err' ? 'error' : $this->detectLevel($line), $line);
                            }
                        }
                    });

                if (trim($buffer) !== '') {
                    $emit($this->detectLevel($buffer), trim($buffer));
                }

                $status = $result->successful() ? 'success' : 'failed';
                $emit(
                    $status === 'success' ? 'success' : 'error',
                    $status === 'success'
                        ? 'Commande terminée avec succès.'
                        : 'Commande terminée avec le code '.$result->exitCode().'.'
                );
            } catch (Throwable $e) {
                report($e);
                $emit('error', 'Erreur : '.$e->getMessage());
            } finally {
                Cache::forever(self::LAST_RUN_KEY, [
                    'status' => $status,
                    'started_at' => $startedAt->toIso8601String(),
                    'finished_at' => now()->toIso8601String(),
                    'user' => $user?->name,
                    'logs' => $logs,
                ]);
                $emit('done', $status);
                $lock->release();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function detectLevel(string $line): string
    {
        return match (true) {
            str_contains($line, '✓') => 'success',
            (bool) preg_match('/échec|erreur|error|exception/iu', $line) => 'error',
            (bool) preg_match('/^\s*(\[\d+\/\d+\]|===)/u', $line) => 'comment',
            default => 'info',
        };
    }

    private function denyIfNotAdmin(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['admin', 'Admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Seuls les administrateurs peuvent effectuer cette action.',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
