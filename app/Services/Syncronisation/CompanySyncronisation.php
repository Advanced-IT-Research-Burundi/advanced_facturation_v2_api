<?php

namespace App\Services\Syncronisation;

use App\Models\Company;
use App\Models\TruckSyncroniser;
use App\Services\SyncMainApp;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanySyncronisation
{
    public function syncCompanies(): array|string
    {
        $lastId = TruckSyncroniser::where('model_name', 'Company')
            ->latest('id')
            ->value('last_id') ?? 0;

        try {
            $response = (new SyncMainApp)->get('/companies_sync/'.$lastId);
            $companies = $response['data'] ?? [];

            if ($companies === []) {
                return ['success' => true, 'total_synced' => 0];
            }

            return DB::transaction(function () use ($companies) {
                $maxId = collect($companies)->max('id');
                $synced = 0;

                foreach ($companies as $remoteCompany) {
                    Company::updateOrCreate(
                        ['id' => $remoteCompany['id']],
                        [
                            'name' => $remoteCompany['name'] ?? null,
                            'tp_type' => $remoteCompany['tp_type'] ?? null,
                            'tp_name' => $remoteCompany['tp_name'] ?? null,
                            'tp_TIN' => $remoteCompany['tp_TIN'] ?? null,
                            'tp_trade_number' => $remoteCompany['tp_trade_number'] ?? null,
                            'tp_postal_number' => $remoteCompany['tp_postal_number'] ?? null,
                            'tp_phone_number' => $remoteCompany['tp_phone_number'] ?? null,
                            'tp_address_province' => $remoteCompany['tp_address_province'] ?? null,
                            'tp_address_commune' => $remoteCompany['tp_address_commune'] ?? null,
                            'tp_address_quartier' => $remoteCompany['tp_address_quartier'] ?? null,
                            'tp_address_avenue' => $remoteCompany['tp_address_avenue'] ?? null,
                            'tp_address_rue' => $remoteCompany['tp_address_rue'] ?? null,
                            'tp_address_number' => $remoteCompany['tp_address_number'] ?? null,
                            'tp_fiscal_center' => $remoteCompany['tp_fiscal_center'] ?? null,
                            'tp_activity_sector' => $remoteCompany['tp_activity_sector'] ?? null,
                            'tp_legal_form' => $remoteCompany['tp_legal_form'] ?? null,
                            'vat_taxpayer' => $remoteCompany['vat_taxpayer'] ?? null,
                            'ct_taxpayer' => $remoteCompany['ct_taxpayer'] ?? null,
                            'tl_taxpayer' => $remoteCompany['tl_taxpayer'] ?? null,
                            'system_or_device_id' => $remoteCompany['system_or_device_id'] ?? null,
                            'default_currency' => $remoteCompany['default_currency'] ?? null,
                            'domain' => $remoteCompany['domain'] ?? null,
                            'user_id' => $remoteCompany['user_id'] ?? null,
                            'company_logo' => $remoteCompany['company_logo'] ?? null,
                            'created_at' => $remoteCompany['created_at'] ?? now(),
                            'updated_at' => $remoteCompany['updated_at'] ?? now(),
                        ]
                    );

                    $synced++;
                }

                TruckSyncroniser::create([
                    'model_name' => 'Company',
                    'last_id' => $maxId,
                ]);

                return [
                    'success' => true,
                    'total_synced' => $synced,
                    'last_id' => $maxId,
                ];
            });
        } catch (Exception $exception) {
            Log::error('Company synchronization failed: '.$exception->getMessage());

            return $exception->getMessage();
        }
    }
}
