<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FourinsseurFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_number' => fake()->phoneNumber(),
            'nif' => fake()->text(),
            'email' => fake()->safeEmail(),
            'address' => fake()->text(),
            'company_id' => fake()->word(),
            'user_id' => fake()->word(),
        ];
    }
}
