<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseProductFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->randomFloat(0, 0, 9999999999.),
            'unit_price' => fake()->randomFloat(0, 0, 9999999999.),
            'currency' => fake()->word(),
            'last_stock_movement_id' => StockMovement::factory(),
            'user_id' => User::factory(),
            'last_stock_movement_id_id' => StockMovement::factory(),
        ];
    }
}
