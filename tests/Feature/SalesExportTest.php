<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class SalesExportTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->user);
    }

    private function createInvoice(string $number, array $attributes = []): Invoice
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create(array_merge([
            'invoice_number' => $number,
            'invoice_date' => '2026-03-15 10:30:00',
            'invoice_type' => 'FN',
            'invoice_currency' => 'BIF',
            'payment_type' => '1',
            'is_validated' => true,
            'is_cancelled' => false,
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'created_by_id' => $this->user->id,
        ], $attributes)));

        $invoice->invoiceItems()->create([
            'item_designation' => "Article {$number}",
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

    private function downloadSheet(array $params = []): Worksheet
    {
        $response = $this->get('/api/export/sales?'.http_build_query(array_merge([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ], $params)));

        $response->assertOk();

        return IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
    }

    public function test_exports_one_row_per_item_with_totals(): void
    {
        $invoice = $this->createInvoice('FACT-1');
        $invoice->invoiceItems()->create([
            'item_designation' => 'Second article',
            'item_quantity' => 1,
            'item_price' => 500,
            'item_price_nvat' => 500,
            'vat' => 0,
            'item_price_wvat' => 500,
            'item_total_amount' => 500,
            'user_id' => $this->user->id,
        ]);

        $sheet = $this->downloadSheet();

        $this->assertSame('FACT-1', $sheet->getCell('A2')->getValue());
        $this->assertSame('15/03/2026 10:30', $sheet->getCell('B2')->getFormattedValue());
        $this->assertSame('Espèces', $sheet->getCell('G2')->getValue());
        $this->assertSame('Article FACT-1', $sheet->getCell('H2')->getValue());
        $this->assertEquals(2000, $sheet->getCell('K2')->getValue());
        $this->assertEquals(360, $sheet->getCell('L2')->getValue());
        $this->assertEquals(2360, $sheet->getCell('M2')->getValue());
        $this->assertSame('Second article', $sheet->getCell('H3')->getValue());

        $this->assertSame('TOTAL', $sheet->getCell('A4')->getValue());
        $this->assertEquals(2500, $sheet->getCell('K4')->getCalculatedValue());
        $this->assertEquals(360, $sheet->getCell('L4')->getCalculatedValue());
        $this->assertEquals(2860, $sheet->getCell('M4')->getCalculatedValue());
        $this->assertSame('2,860.00', $sheet->getCell('M4')->getFormattedValue());
    }

    public function test_only_exports_sales_of_the_chosen_period(): void
    {
        $this->createInvoice('AVANT', ['invoice_date' => '2026-02-28 23:59:00']);
        $this->createInvoice('DEBUT', ['invoice_date' => '2026-03-01 00:00:00']);
        $this->createInvoice('FIN', ['invoice_date' => '2026-03-31 23:59:00']);
        $this->createInvoice('APRES', ['invoice_date' => '2026-04-01 00:00:00']);

        $sheet = $this->downloadSheet();

        $this->assertSame('DEBUT', $sheet->getCell('A2')->getValue());
        $this->assertSame('FIN', $sheet->getCell('A3')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('A4')->getValue());
    }

    public function test_excludes_invoices_that_are_not_sales(): void
    {
        $this->createInvoice('VENTE');
        $this->createInvoice('ANNULEE', ['is_cancelled' => true]);
        $this->createInvoice('PROFORMA', ['invoice_type' => 'FP']);
        $this->createInvoice('AVOIR', ['invoice_type' => 'FA']);
        $this->createInvoice('BROUILLON', ['is_validated' => false]);

        $sheet = $this->downloadSheet();

        $this->assertSame('VENTE', $sheet->getCell('A2')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('A3')->getValue());
    }

    public function test_can_include_unvalidated_invoices(): void
    {
        $this->createInvoice('BROUILLON', ['is_validated' => false]);

        $sheet = $this->downloadSheet(['include_unvalidated' => 1]);

        $this->assertSame('BROUILLON', $sheet->getCell('A2')->getValue());
        $this->assertSame('Non validée', $sheet->getCell('D2')->getValue());
    }

    public function test_does_not_export_sales_of_another_company(): void
    {
        $this->createInvoice('MOI');

        $otherCompany = Company::factory()->create();
        $this->createInvoice('AUTRE', ['company_id' => $otherCompany->id]);

        $sheet = $this->downloadSheet();

        $this->assertSame('MOI', $sheet->getCell('A2')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('A3')->getValue());
    }

    public function test_text_starting_with_equals_is_not_a_formula(): void
    {
        $invoice = $this->createInvoice('FACT-1');
        $invoice->invoiceItems()->update(['item_designation' => '=1+1']);

        $sheet = $this->downloadSheet();

        $this->assertSame('=1+1', $sheet->getCell('H2')->getValue());
        $this->assertSame('s', $sheet->getCell('H2')->getDataType());
    }

    public function test_returns_not_found_when_period_has_no_sales(): void
    {
        $this->getJson('/api/export/sales?start_date=2026-03-01&end_date=2026-03-31')
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_validates_the_period(): void
    {
        $this->getJson('/api/export/sales')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'end_date']);

        $this->getJson('/api/export/sales?start_date=2026-03-31&end_date=2026-03-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }
}
