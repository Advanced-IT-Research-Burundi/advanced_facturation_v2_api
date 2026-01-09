<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehousesSeeder extends Seeder
{
    public function run()
    {
        $warehouses = [];
        
        $warehouseNames = [
            'Main Warehouse', 'Storage Facility A', 'Distribution Center',
            'Cold Storage', 'Retail Storage', 'Backup Warehouse',
            'Regional Depot', 'Secondary Storage', 'Temporary Storage',
            'Export Warehouse'
        ];
        
        for ($i = 1; $i <= 10; $i++) {
            $companyId = $i <= 3 ? 1 : ($i <= 6 ? 2 : ($i <= 8 ? 3 : ($i == 9 ? 4 : 5)));
            $userId = $i <= 10 ? $i : 1;
            
            $warehouses[] = [
                'name' => $warehouseNames[$i - 1],
                'location' => 'Location ' . $i . ', City ' . $i,
                'company_id' => $companyId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('warehouses')->insert($warehouses);
    }
}
