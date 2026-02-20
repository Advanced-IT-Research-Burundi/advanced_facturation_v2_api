<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'room_number' => fake()->unique()->numerify('###'),
            'name' => fake()->optional()->words(2, true),
            'type' => fake()->randomElement(['standard', 'double', 'suite', 'vip']),
            'floor' => fake()->optional()->randomElement(['1', '2', '3', 'RDC']),
            'capacity' => fake()->numberBetween(1, 4),
            'price_per_night' => fake()->randomElement([30000, 50000, 75000, 100000, 150000]),
            'status' => 'available',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
