<?php

namespace App\Services\Syncronisation;

use App\Models\Company;
use App\Models\TruckSyncroniser;
use App\Models\User;
use App\Services\SyncMainApp;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserSyncronisation
{
    public function syncUsers(): array|string
    {
        $lastId = TruckSyncroniser::where('model_name', 'User')
            ->latest('id')
            ->value('last_id') ?? 0;

        try {
            $response = (new SyncMainApp())->get('/users_sync/'.$lastId);
            $users = $response['data'] ?? [];

            if ($users === []) {
                return ['success' => true, 'total_synced' => 0];
            }

            return DB::transaction(function () use ($users) {
                $maxId = collect($users)->max('id');
                $synced = 0;

                foreach ($users as $remoteUser) {
                    $localUser = User::find($remoteUser['id']);
                    $companyId = ! empty($remoteUser['company_id'])
                        && Company::whereKey($remoteUser['company_id'])->exists()
                        ? $remoteUser['company_id']
                        : null;

                    $localUser = User::updateOrCreate(
                        ['id' => $remoteUser['id']],
                        [
                            'name' => $remoteUser['name'] ?? null,
                            'email' => $remoteUser['email'],
                            'company_id' => $companyId,
                            'user_id' => $remoteUser['user_id'] ?? null,
                            'is_server' => $remoteUser['is_server'] ?? false,
                            'server_code' => $remoteUser['server_code'] ?? null,
                            'password' => $localUser?->password ?? Hash::make(Str::random(40)),
                            'created_at' => $remoteUser['created_at'] ?? now(),
                            'updated_at' => $remoteUser['updated_at'] ?? now(),
                        ]
                    );

                    $synced++;
                }

                TruckSyncroniser::create([
                    'model_name' => 'User',
                    'last_id' => $maxId,
                ]);

                return [
                    'success' => true,
                    'total_synced' => $synced,
                    'last_id' => $maxId,
                ];
            });
        } catch (Exception $exception) {
            Log::error($exception->getMessage());

            return $exception->getMessage();
        }
    }
}
