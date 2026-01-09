<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run()
    {
        $companies = [];

        for ($i = 1; $i <= 10; $i++) {
            $companies[] = [
                'name' => 'Tech Solutions ' . $i . ' SARL',
                'tp_type' => 'SARL',
                'tp_name' => 'TECH SOLUTIONS ' . $i . ' SARL',
                'tp_TIN' => '12345678901' . $i,
                'tp_trade_number' => 'RC1234' . $i,
                'tp_postal_number' => 'BP 123' . $i,
                'tp_phone_number' => '+2438112233' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'tp_address_province' => 'Kinshasa',
                'tp_address_commune' => 'Gombe',
                'tp_address_quartier' => 'Commercial',
                'tp_address_avenue' => 'Avenue des Aviateurs',
                'tp_address_rue' => 'Rue 12',
                'tp_address_number' => 'No. 3' . $i,
                'tp_fiscal_center' => 'Kinshasa-Gombe',
                'tp_activity_sector' => 'Informatique et Services',
                'tp_legal_form' => 'SARL',
                'vat_taxpayer' => 'YES',
                'ct_taxpayer' => 'YES',
                'tl_taxpayer' => 'YES',
                'system_or_device_id' => 'DEV00' . $i,
                'default_currency' => 'CDF',
                'user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('companies')->insert($companies);
    }
}
