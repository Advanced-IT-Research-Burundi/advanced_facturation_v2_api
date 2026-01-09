<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'item_designation' => fake()->word(),
            'item_quantity' => fake()->randomFloat(0, 0, 9999999999.),
            'item_price' => fake()->randomFloat(0, 0, 9999999999.),
            'item_ct' => fake()->randomFloat(0, 0, 9999999999.),
            'item_tl' => fake()->randomFloat(0, 0, 9999999999.),
            'item_ott_tax' => fake()->randomFloat(0, 0, 9999999999.),
            'item_tsce_tax' => fake()->randomFloat(0, 0, 9999999999.),
            'item_price_nvat' => fake()->randomFloat(0, 0, 9999999999.),
            'vat' => fake()->randomFloat(0, 0, 9999999999.),
            'item_price_wvat' => fake()->randomFloat(0, 0, 9999999999.),
            'item_total_amount' => fake()->randomFloat(0, 0, 9999999999.),
            'user_id' => User::factory(),
        ];
    }
}
