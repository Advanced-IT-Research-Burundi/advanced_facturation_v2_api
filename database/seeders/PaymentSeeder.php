<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentSeeder extends Seeder
{
     public function run()
    {
        $invoiceItems = [];
        
        $productDesignations = [
            'Smartphone Galaxy S24', 'Laptop Dell XPS 15', 'Wireless Headphones',
            'Paracetamol 500mg', 'Vitamin C 1000mg', 'Cement 50kg',
            'Iron Rod 12mm', 'Rice 25kg', 'Cooking Oil 5L',
            'Cotton Fabric 1m', 'Jeans Pants', 'Programming Guide'
        ];
        
        for ($i = 1; $i <= 30; $i++) {
            $invoiceId = $i <= 10 ? 1 : ($i <= 20 ? 2 : ($i <= 25 ? 3 : 4));
            $userId = $i <= 10 ? 1 : ($i <= 20 ? 2 : 3);
            
            $quantity = rand(1, 10);
            $price = rand(1000, 50000);
            $vat = $price * 0.16;
            $totalAmount = ($price + $vat) * $quantity;
            
            $invoiceItems[] = [
                'invoice_id' => $invoiceId,
                'item_designation' => $productDesignations[rand(0, 11)],
                'item_quantity' => $quantity,
                'item_price' => $price,
                'item_ct' => $i % 3 == 0 ? rand(1, 10) : null,
                'item_tl' => $i % 4 == 0 ? rand(1, 5) : null,
                'item_ott_tax' => $i % 5 == 0 ? rand(1, 8) : null,
                'item_tsce_tax' => $i % 6 == 0 ? rand(1, 12) : null,
                'item_price_nvat' => $price,
                'vat' => $vat,
                'item_price_wvat' => $price + $vat,
                'item_total_amount' => $totalAmount,
                'user_id' => $userId,
                'created_at' => now(),
            ];
        }

        DB::table('invoice_items')->insert($invoiceItems);
    }
}
