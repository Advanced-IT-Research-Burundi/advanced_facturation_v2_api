<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->word(),
            'invoice_date' => fake()->dateTime(),
            'invoice_type' => fake()->word(),
            'invoice_identifier' => fake()->word(),
            'invoice_currency' => fake()->word(),
            'tp_type' => fake()->word(),
            'tp_name' => fake()->word(),
            'tp_TIN' => fake()->word(),
            'tp_trade_number' => fake()->word(),
            'tp_phone_number' => fake()->word(),
            'tp_fiscal_center' => fake()->word(),
            'vat_taxpayer' => fake()->word(),
            'ct_taxpayer' => fake()->word(),
            'tl_taxpayer' => fake()->word(),
            'customer_name' => fake()->word(),
            'customer_TIN' => fake()->word(),
            'customer_address' => fake()->word(),
            'vat_customer_payer' => fake()->word(),
            'invoice_amount_nvat' => fake()->randomFloat(2, 0, 9999999999999.99),
            'invoice_vat_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'invoice_total_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'invoice_registered_number' => fake()->word(),
            'invoice_registered_date' => fake()->dateTime(),
            'electronic_signature' => fake()->text(),
            'obr_submission_status' => fake()->randomElement(["PENDING","SENT","ACCEPTED","REJECTED"]),
            'obr_response_message' => fake()->text(),
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory()->create()->created_by,
            'user_id' => User::factory(),
            'created_by_id' => User::factory(),
        ];
    }
}
