<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->word(),
            'customer_TIN' => fake()->word(),
            'customer_phone' => fake()->word(),
            'customer_address' => fake()->word(),
            'vat_customer_payer' => fake()->word(),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
        ];
    }
}
