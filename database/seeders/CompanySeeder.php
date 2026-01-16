<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run()
    {
        DB::table('companies')->upsert([
            [
                'name' => 'Advanced IT and Research Burundi',
                'tp_type' => 1,
                'tp_name' => 'ADVANCED IT AND RESEARCH BURUNDI SARL',
                'tp_TIN' => '12345678901234',
                'tp_trade_number' => 'RC/BDI/2025/001',
                'tp_postal_number' => 'BP 5000',
                'tp_phone_number' => '+25779123456',
                'tp_address_province' => 'Bujumbura',
                'tp_address_commune' => 'Bujumbura',
                'tp_address_quartier' => 'Nyakabiga',
                'tp_address_avenue' => 'Avenue de l\'OUA',
                'tp_address_rue' => 'Rue de Techno',
                'tp_address_number' => '123',
                'tp_fiscal_center' => 'DMC',
                'tp_activity_sector' => 'Informatique et Services de Recherche',
                'tp_legal_form' => 'SARL',
                'vat_taxpayer' => 'YES',
                'ct_taxpayer' => 'YES',
                'tl_taxpayer' => 'YES',
                'system_or_device_id' => 'AIRT-DEV-001',
                'default_currency' => 'FBu',
                'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tech Solutions Test',
                'tp_type' => 2,
                'tp_name' => 'TECH SOLUTIONS TEST SARL',
                'tp_TIN' => '98765432109876',
                'tp_trade_number' => 'RC/BDI/2025/002',
                'tp_postal_number' => 'BP 6000',
                'tp_phone_number' => '+25778987654',
                'tp_address_province' => 'Bujumbura',
                'tp_address_commune' => 'Bujumbura',
                'tp_address_quartier' => 'Mukaza',
                'tp_address_avenue' => 'Avenue de Commerce',
                'tp_address_rue' => 'Rue 45',
                'tp_address_number' => '456',
                'tp_fiscal_center' => 'DGC',
                'tp_activity_sector' => 'Services Informatiques',
                'tp_legal_form' => 'SARL',
                'vat_taxpayer' => 'YES',
                'ct_taxpayer' => 'YES',
                'tl_taxpayer' => 'YES',
                'system_or_device_id' => 'TECH-DEV-002',
                'default_currency' => 'FBu',
                'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['tp_TIN'], [
            'name',
            'tp_type',
            'tp_name',
            'tp_trade_number',
            'tp_postal_number',
            'tp_phone_number',
            'tp_address_province',
            'tp_address_commune',
            'tp_address_quartier',
            'tp_address_avenue',
            'tp_address_rue',
            'tp_address_number',
            'tp_fiscal_center',
            'tp_activity_sector',
            'tp_legal_form',
            'vat_taxpayer',
            'ct_taxpayer',
            'tl_taxpayer',
            'system_or_device_id',
            'default_currency',
            'user_id',
            'updated_at'
        ]);
    }
}
