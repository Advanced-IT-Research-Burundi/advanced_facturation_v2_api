<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseProductQuantityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
    }

    /**
     * Test que la quantité du produit diminue quand on l'ajoute à un entrepôt
     */
    public function test_product_quantity_decreases_when_added_to_warehouse(): void
    {
        // Créer un produit avec 100kg de quantité
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'quantite' => 100,
            'item_code' => 'PROD001',
            'item_designation' => 'Produit Test',
        ]);

        // Créer un entrepôt
        $warehouse = Warehouse::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Entrepôt du Nord',
        ]);

        // Ajouter 45kg du produit à l'entrepôt
        $response = $this->postJson('/api/warehouse-products', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 45,
            'unit_price' => 100,
            'currency' => 'BIF',
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        // Vérifier que la quantité du produit a diminué de 100 à 55
        $product->refresh();
        $this->assertEquals(55, $product->quantite);

        // Vérifier que la quantité dans l'entrepôt est bien 45
        $warehouseProduct = WarehouseProduct::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $this->assertEquals(45, $warehouseProduct->quantity);
    }

    /**
     * Test qu'on ne peut pas ajouter plus que la quantité disponible
     */
    public function test_cannot_add_more_than_available_quantity(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'quantite' => 50,
            'item_code' => 'PROD002',
        ]);

        $warehouse = Warehouse::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Essayer d'ajouter 100kg alors qu'il n'y a que 50kg disponibles
        $response = $this->postJson('/api/warehouse-products', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'unit_price' => 100,
            'currency' => 'BIF',
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        // Vérifier que la quantité du produit n'a pas changé
        $product->refresh();
        $this->assertEquals(50, $product->quantite);
    }

    /**
     * Test que la quantité est restaurée quand on supprime un produit de l'entrepôt
     */
    public function test_product_quantity_restored_when_removed_from_warehouse(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'quantite' => 100,
        ]);

        $warehouse = Warehouse::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Ajouter le produit à l'entrepôt
        $warehouseProduct = WarehouseProduct::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'company_id' => $this->company->id,
            'quantity' => 30,
            'unit_price' => 100,
            'currency' => 'BIF',
            'user_id' => $this->user->id,
        ]);

        // La quantité du produit devrait maintenant être 70 (non testé ici car créé directement)
        $product->update(['quantite' => 70]);

        // Supprimer le produit de l'entrepôt
        $response = $this->deleteJson("/api/warehouse-products/{$warehouseProduct->id}");

        $response->assertStatus(200);

        // Vérifier que la quantité du produit a été restaurée à 100
        $product->refresh();
        $this->assertEquals(100, $product->quantite);
    }

    /**
     * Test que la quantité s'ajuste quand on modifie la quantité dans l'entrepôt
     */
    public function test_product_quantity_adjusts_when_warehouse_quantity_changes(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'quantite' => 100,
        ]);

        $warehouse = Warehouse::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Ajouter 30kg à l'entrepôt
        $response = $this->postJson('/api/warehouse-products', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'unit_price' => 100,
            'currency' => 'BIF',
            'user_id' => $this->user->id,
        ]);

        $warehouseProduct = WarehouseProduct::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        // La quantité du produit devrait être 70
        $product->refresh();
        $this->assertEquals(70, $product->quantite);

        // Augmenter la quantité dans l'entrepôt à 50kg
        $response = $this->patchJson("/api/warehouse-products/{$warehouseProduct->id}", [
            'quantity' => 50,
        ]);

        $response->assertStatus(200);

        // La quantité du produit devrait maintenant être 50 (70 - 20)
        $product->refresh();
        $this->assertEquals(50, $product->quantite);

        // Diminuer la quantité dans l'entrepôt à 20kg
        $response = $this->patchJson("/api/warehouse-products/{$warehouseProduct->id}", [
            'quantity' => 20,
        ]);

        $response->assertStatus(200);

        // La quantité du produit devrait maintenant être 80 (50 + 30)
        $product->refresh();
        $this->assertEquals(80, $product->quantite);
    }
}
