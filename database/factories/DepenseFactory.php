<?php

namespace Database\Factories;

use App\Models\DepenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepenseFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'montant' => fake()->randomFloat(0, 0, 9999999999.),
            'depense_category_id' => DepenseCategory::factory(),
            'company_id' => fake()->numberBetween(-10000, 10000),
            'justification_file' => fake()->word(),
        ];
    }
}
