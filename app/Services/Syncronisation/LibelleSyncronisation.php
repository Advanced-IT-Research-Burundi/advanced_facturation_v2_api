<?php

namespace App\Services\Syncronisation;

use App\Models\Company;
use App\Models\Libelle;
use App\Models\TruckSyncroniser;
use App\Models\User;
use App\Services\SyncMainApp;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibelleSyncronisation
{
    public function syncLibelles(): array|string
    {
        $lastId = TruckSyncroniser::where('model_name', 'Libelle')
            ->latest('id')
            ->value('last_id') ?? 0;

        try {
            $response = (new SyncMainApp)->get('/libelles_sync/'.$lastId);
            $libelles = $response['data'] ?? [];

            if ($libelles === []) {
                return ['success' => true, 'total_synced' => 0];
            }

            $localUserId = User::query()->orderBy('id')->value('id');

            $resolveUserId = static function ($remoteUserId) use ($localUserId): ?int {
                if (! $remoteUserId) {
                    return $localUserId;
                }

                return User::whereKey($remoteUserId)->exists()
                    ? (int) $remoteUserId
                    : $localUserId;
            };

            $resolveCompanyId = static function ($remoteCompanyId): ?int {
                return $remoteCompanyId && Company::whereKey($remoteCompanyId)->exists()
                    ? (int) $remoteCompanyId
                    : null;
            };

            DB::transaction(function () use ($libelles, &$maxId, $resolveUserId, $resolveCompanyId) {
                $maxId = collect($libelles)->max('id');

                foreach ($libelles as $libelle) {
                    $companyId = $resolveCompanyId($libelle['company_id'] ?? null);

                    if ($companyId === null) {
                        Log::warning('Libelle sync skipped: company does not exist.', [
                            'name' => $libelle['name'],
                            'company_id' => $libelle['company_id'] ?? null,
                        ]);

                        continue;
                    }

                    Libelle::updateOrCreate(
                        [
                            'name' => $libelle['name'],
                            'company_id' => $companyId,
                        ],
                        [
                            'description' => $libelle['description'] ?? null,
                            'price' => $libelle['price'] ?? null,
                            'tva' => $libelle['tva'] ?? null,
                            'user_id' => $resolveUserId($libelle['user_id'] ?? null),
                            'created_at' => $libelle['created_at'] ?? now(),
                            'updated_at' => $libelle['updated_at'] ?? now(),
                        ]
                    );
                }

                TruckSyncroniser::create([
                    'model_name' => 'Libelle',
                    'last_id' => $maxId,
                ]);
            });

            return ['success' => true, 'total_synced' => count($libelles), 'last_id' => $maxId];
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return $exception->getMessage();
        }
    }
}
