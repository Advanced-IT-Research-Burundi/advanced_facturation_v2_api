<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepenseCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'company_id' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
