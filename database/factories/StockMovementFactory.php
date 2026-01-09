<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'system_or_device_id' => fake()->word(),
            'item_code' => fake()->word(),
            'item_designation' => fake()->word(),
            'item_quantity' => fake()->randomFloat(0, 0, 9999999999.),
            'item_measurement_unit' => fake()->word(),
            'item_purchase_or_sale_price' => fake()->randomFloat(0, 0, 9999999999.),
            'item_purchase_or_sale_currency' => fake()->word(),
            'item_movement_type' => fake()->word(),
            'item_movement_invoice_ref' => fake()->word(),
            'item_movement_description' => fake()->word(),
            'item_movement_date' => fake()->dateTime(),
            'obr_submission_status' => fake()->randomElement(["PENDING","SENT","ACCEPTED","REJECTED"]),
            'obr_response_message' => fake()->text(),
            'obr_sent_at' => fake()->dateTime(),
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'created_by' => User::factory()->create()->created_by,
            'user_id' => User::factory(),
            'created_by_id' => User::factory(),
        ];
    }
}
