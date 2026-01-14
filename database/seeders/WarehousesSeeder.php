<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehousesSeeder extends Seeder
{
    public function run()
    {
        $warehouses = [];
        
        $warehouseNames = [
            'MAGASIN', 'Stock Facility A'
        ];

        foreach ($warehouseNames as $warehouseName) {
            Warehouse::create(
                [
                    'name' => $warehouseName,
                'location' => 'Burundi Bujumbura  ',
                'company_id' => 1,
                'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                ]
            );
        }
    }
}
