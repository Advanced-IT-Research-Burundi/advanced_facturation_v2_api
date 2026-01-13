<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AppConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'config_key' => fake()->word(),
            'value' => fake()->text(),
        ];
    }
}
