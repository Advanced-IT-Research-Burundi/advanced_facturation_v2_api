<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'item_code' => fake()->word(),
            'item_designation' => fake()->word(),
            'item_measurement_unit' => fake()->word(),
            'barcode' => fake()->word(),
            'vat_rate' => fake()->randomFloat(0, 0, 9999999999.),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
        ];
    }
}
