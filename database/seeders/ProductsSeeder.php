<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class ProductsSeeder extends Seeder
{
    public function run()
    {
        $products = [
            // Electronics
            [
                'item_code' => 'ELEC001',
                'item_designation' => 'Smartphone Galaxy S24',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567890',
                'vat_rate' => 16.0,
                'company_id' => 1,
                'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'ELEC002',
                'item_designation' => 'Laptop Dell XPS 15',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567891',
                'vat_rate' => 16.0,
                'company_id' => 1,
                'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'ELEC003',
                'item_designation' => 'Wireless Headphones',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567892',
                'vat_rate' => 16.0,
                'company_id' => 1,
                'user_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Pharmacy
            [
                'item_code' => 'PHARM001',
                'item_designation' => 'Paracetamol 500mg',
                'item_measurement_unit' => 'Box',
                'barcode' => '8901234567893',
                'vat_rate' => 0.0,
                'company_id' => 4,
                'user_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'PHARM002',
                'item_designation' => 'Vitamin C 1000mg',
                'item_measurement_unit' => 'Bottle',
                'barcode' => '8901234567894',
                'vat_rate' => 16.0,
                'company_id' => 4,
                'user_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Construction
            [
                'item_code' => 'CONST001',
                'item_designation' => 'Cement 50kg',
                'item_measurement_unit' => 'Bag',
                'barcode' => '8901234567895',
                'vat_rate' => 16.0,
                'company_id' => 5,
                'user_id' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'CONST002',
                'item_designation' => 'Iron Rod 12mm',
                'item_measurement_unit' => 'Ton',
                'barcode' => '8901234567896',
                'vat_rate' => 16.0,
                'company_id' => 5,
                'user_id' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Food
            [
                'item_code' => 'FOOD001',
                'item_designation' => 'Rice 25kg',
                'item_measurement_unit' => 'Bag',
                'barcode' => '8901234567897',
                'vat_rate' => 0.0,
                'company_id' => 6,
                'user_id' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'FOOD002',
                'item_designation' => 'Cooking Oil 5L',
                'item_measurement_unit' => 'Bottle',
                'barcode' => '8901234567898',
                'vat_rate' => 16.0,
                'company_id' => 6,
                'user_id' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Textile
            [
                'item_code' => 'TEXT001',
                'item_designation' => 'Cotton Fabric 1m',
                'item_measurement_unit' => 'Meter',
                'barcode' => '8901234567899',
                'vat_rate' => 16.0,
                'company_id' => 8,
                'user_id' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'TEXT002',
                'item_designation' => 'Jeans Pants',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567800',
                'vat_rate' => 16.0,
                'company_id' => 8,
                'user_id' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Books
            [
                'item_code' => 'BOOK001',
                'item_designation' => 'Programming Guide',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567801',
                'vat_rate' => 16.0,
                'company_id' => 10,
                'user_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'BOOK002',
                'item_designation' => 'Business Strategy',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567802',
                'vat_rate' => 16.0,
                'company_id' => 10,
                'user_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Automotive
            [
                'item_code' => 'AUTO001',
                'item_designation' => 'Engine Oil 5W-30',
                'item_measurement_unit' => 'Liter',
                'barcode' => '8901234567803',
                'vat_rate' => 16.0,
                'company_id' => 3,
                'user_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => 'AUTO002',
                'item_designation' => 'Car Battery 12V',
                'item_measurement_unit' => 'Unit',
                'barcode' => '8901234567804',
                'vat_rate' => 16.0,
                'company_id' => 3,
                'user_id' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('products')->insert($products);
    }
}
