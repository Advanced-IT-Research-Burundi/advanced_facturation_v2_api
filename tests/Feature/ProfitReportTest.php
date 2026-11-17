<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Depense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitReportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->product = Product::factory()->create([
            'company_id' => $this->company->id,
            'item_designation' => 'Produit bénéfice',
            'price' => 1000,
        ]);
        $this->actingAs($this->user);
    }

    private function createInvoice(): Invoice
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'invoice_number' => 'FACT-PROFIT-1',
            'invoice_date' => '2026-03-15 10:30:00',
            'invoice_type' => 'FN',
            'invoice_currency' => 'BIF',
            'payment_type' => '1',
            'is_validated' => true,
            'is_cancelled' => false,
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'created_by_id' => $this->user->id,
        ]));

        // 2 unités : CA HTVA 2000, coût 2 × 600 = 1200, bénéfice 800
        $invoice->invoiceItems()->create([
            'product_id' => $this->product->id,
            'purchase_price' => 600,
            'item_designation' => $this->product->item_designation,
            'item_quantity' => 2,
            'item_price' => 1000,
            'item_price_nvat' => 1000,
            'vat' => 18,
            'item_price_wvat' => 1180,
            'item_total_amount' => 2360,
            'user_id' => $this->user->id,
        ]);

        return $invoice;
    }

    public function test_report_groups_profit_by_product_period_and_sale(): void
    {
        $invoice = $this->createInvoice();

        $response = $this->getJson('/api/reports/profit')->assertOk()->assertJson(['success' => true]);
        $data = $response->json('data');

        $this->assertEquals(2000, $data['summary']['revenue']);
        $this->assertEquals(1200, $data['summary']['cost']);
        $this->assertEquals(800, $data['summary']['profit']);
        $this->assertEquals(40, $data['summary']['margin']);
        $this->assertEquals(1, $data['summary']['invoices_count']);

        $this->assertCount(1, $data['by_product']);
        $this->assertEquals($this->product->id, $data['by_product'][0]['product_id']);
        $this->assertEquals(800, $data['by_product'][0]['profit']);

        $this->assertCount(1, $data['by_period']);
        $this->assertEquals('2026-03-15', $data['by_period'][0]['date']);
        $this->assertEquals(800, $data['by_period'][0]['profit']);

        $this->assertCount(1, $data['by_sale']);
        $this->assertEquals($invoice->id, $data['by_sale'][0]['invoice_id']);
        $this->assertEquals(800, $data['by_sale'][0]['profit']);
    }

    public function test_report_shows_expenses_separately_and_computes_net_profit(): void
    {
        $this->createInvoice();

        Depense::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Achat fournitures',
            'montant' => 200,
            'user_id' => $this->user->id,
            'status' => Depense::STATUS_APPROVED,
            'created_at' => '2026-03-10 10:00:00',
        ]);

        // Une dépense en attente ne doit pas être comptée.
        Depense::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Dépense en attente',
            'montant' => 500,
            'user_id' => $this->user->id,
            'status' => Depense::STATUS_PENDING,
            'created_at' => '2026-03-11 10:00:00',
        ]);

        $data = $this->getJson('/api/reports/profit')->assertOk()->json('data');

        // Ventes (800 de bénéfice brut) et dépenses affichées à part.
        $this->assertEquals(2000, $data['summary']['revenue']);
        $this->assertEquals(800, $data['summary']['profit']);
        $this->assertEquals(200, $data['summary']['expenses_total']);
        $this->assertEquals(600, $data['summary']['net_profit']);
        $this->assertEquals(30, $data['summary']['net_margin']);

        $this->assertEquals(200, $data['expenses']['total']);
        $this->assertCount(1, $data['expenses']['items']);
        $this->assertEquals('Achat fournitures', $data['expenses']['items'][0]['name']);
        $this->assertEquals(200, $data['expenses']['by_category'][0]['total']);
        $this->assertEquals('2026-03-10', $data['expenses']['by_period'][0]['date']);
    }

    public function test_report_honors_date_and_product_filters(): void
    {
        $this->createInvoice();

        $this->getJson('/api/reports/profit?start_date=2026-04-01&end_date=2026-04-30')
            ->assertOk()
            ->assertJsonPath('data.summary.profit', 0);

        $this->getJson('/api/reports/profit?product_id='.$this->product->id)
            ->assertOk()
            ->assertJsonPath('data.summary.profit', 800);

        $otherProduct = Product::factory()->create(['company_id' => $this->company->id]);
        $this->getJson('/api/reports/profit?product_id='.$otherProduct->id)
            ->assertOk()
            ->assertJsonPath('data.summary.profit', 0);
    }
}
