<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkWarehouseProductsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
    }

    public function test_bulk_add_attaches_products_and_skips_existing_ones(): void
    {
        $products = Product::factory()->count(3)->create(['company_id' => $this->company->id]);
        $this->attach($products[0]);

        $response = $this->postJson("/api/warehouses/{$this->warehouse->id}/products/bulk-add", [
            'product_ids' => $products->pluck('id')->all(),
        ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'data' => ['added' => 2, 'skipped' => 1]]);
        $this->assertSame(3, WarehouseProduct::where('warehouse_id', $this->warehouse->id)->count());
    }

    public function test_bulk_add_restores_a_previously_removed_product(): void
    {
        $product = Product::factory()->create(['company_id' => $this->company->id]);
        $this->attach($product)->delete();

        $response = $this->postJson("/api/warehouses/{$this->warehouse->id}/products/bulk-add", [
            'product_ids' => [$product->id],
        ]);

        $response->assertOk()->assertJsonPath('data.added', 1);
        $this->assertSame(1, WarehouseProduct::where('warehouse_id', $this->warehouse->id)->count());
    }

    public function test_bulk_remove_keeps_products_with_stock(): void
    {
        $emptyProduct = Product::factory()->create(['company_id' => $this->company->id]);
        $stockedProduct = Product::factory()->create(['company_id' => $this->company->id]);
        $this->attach($emptyProduct);
        $this->attach($stockedProduct, 5);

        $response = $this->postJson("/api/warehouses/{$this->warehouse->id}/products/bulk-remove", [
            'product_ids' => [$emptyProduct->id, $stockedProduct->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.removed', 1)
            ->assertJsonPath('data.skipped_with_stock.0.product_id', $stockedProduct->id);
        $this->assertSame(
            [$stockedProduct->id],
            WarehouseProduct::where('warehouse_id', $this->warehouse->id)->pluck('product_id')->all(),
        );
    }

    public function test_bulk_actions_require_a_valid_product_list(): void
    {
        $this->postJson("/api/warehouses/{$this->warehouse->id}/products/bulk-add", ['product_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_ids');

        $this->postJson("/api/warehouses/{$this->warehouse->id}/products/bulk-remove", ['product_ids' => [999999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_ids.0');
    }

    private function attach(Product $product, float $quantity = 0): WarehouseProduct
    {
        return WarehouseProduct::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'quantity' => $quantity,
            'unit_price' => 0,
            'currency' => 'BIF',
            'user_id' => $this->user->id,
        ]);
    }
}
