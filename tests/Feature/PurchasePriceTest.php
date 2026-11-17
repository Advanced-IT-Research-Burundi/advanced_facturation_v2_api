<?php

namespace Tests\Feature;

use App\Http\Resources\WarehouseProductResource;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePriceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
    }

    public function test_quick_entry_stores_real_purchase_price_separately_from_selling_price(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'price' => 150,
        ]);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        $this->postJson("/api/warehouses/{$warehouse->id}/quick-entry", [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 150,
            'purchase_price' => 100,
            'currency' => 'BIF',
            'movement_type' => 'EN',
        ])->assertOk()->assertJson(['success' => true]);

        $stock = WarehouseProduct::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $this->assertEquals(100, $stock->purchase_price);
        $this->assertEquals(150, $stock->unit_price);
    }

    public function test_bulk_entry_stores_purchase_price_per_item(): void
    {
        $product = Product::factory()->create(['company_id' => $this->company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        $this->postJson("/api/warehouses/{$warehouse->id}/bulk-entry", [
            'movement_type' => 'EN',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 200,
                'purchase_price' => 120,
                'currency' => 'BIF',
            ]],
        ])->assertOk()->assertJson(['success' => true]);

        $stock = WarehouseProduct::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $this->assertEquals(120, $stock->purchase_price);
    }

    public function test_resource_exposes_profit_computed_from_purchase_price(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'price' => 150,
        ]);
        $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

        $stock = WarehouseProduct::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'company_id' => $this->company->id,
            'quantity' => 10,
            'unit_price' => 150,
            'purchase_price' => 100,
            'currency' => 'BIF',
            'user_id' => $this->user->id,
        ]);

        $data = WarehouseProductResource::make($stock)->resolve();

        $this->assertEquals(100, $data['purchase_price']);
        $this->assertEquals(50, $data['profit']);
        $this->assertEquals(33.33, $data['profit_margin']);
        $this->assertEquals(500, $data['total_profit']);
    }
}
