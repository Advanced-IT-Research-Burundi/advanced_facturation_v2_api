<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'tp_type' => fake()->word(),
            'tp_name' => fake()->word(),
            'tp_TIN' => fake()->word(),
            'tp_trade_number' => fake()->word(),
            'tp_postal_number' => fake()->word(),
            'tp_phone_number' => fake()->word(),
            'tp_address_province' => fake()->word(),
            'tp_address_commune' => fake()->word(),
            'tp_address_quartier' => fake()->word(),
            'tp_address_avenue' => fake()->word(),
            'tp_address_rue' => fake()->word(),
            'tp_address_number' => fake()->word(),
            'tp_fiscal_center' => fake()->word(),
            'tp_activity_sector' => fake()->word(),
            'tp_legal_form' => fake()->word(),
            'vat_taxpayer' => fake()->word(),
            'ct_taxpayer' => fake()->word(),
            'tl_taxpayer' => fake()->word(),
            'system_or_device_id' => fake()->word(),
            'default_currency' => fake()->word(),
            'user_id' => User::factory(),
        ];
    }
}
