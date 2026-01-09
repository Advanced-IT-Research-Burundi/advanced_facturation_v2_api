<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomersSeeder extends Seeder
{
    public function run()
    {
        $customers = [];
        
        for ($i = 1; $i <= 20; $i++) {
            $companyId = $i <= 7 ? 1 : ($i <= 14 ? 2 : 3);
            $userId = $i <= 10 ? $i : ($i - 10);
            
            $customers[] = [
                'customer_name' => 'Customer ' . $i . ' Name',
                'customer_TIN' => 'CUST' . str_pad($i, 9, '0', STR_PAD_LEFT),
                'customer_phone' => '+24381' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'customer_address' => 'Address ' . $i . ', City ' . $i,
                'vat_customer_payer' => $i % 2 == 0 ? 'YES' : 'NO',
                'company_id' => $companyId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('customers')->insert($customers);
    }
}
