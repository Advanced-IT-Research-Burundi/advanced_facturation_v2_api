<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

     public function run()
    {
        $this->call([
            CompanySeeder::class,
            UserSeeder::class,
            CustomersSeeder::class,
            ProductsSeeder::class,
            WarehousesSeeder::class
        ]);
    }
}
