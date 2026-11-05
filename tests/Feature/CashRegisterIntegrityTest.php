<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\HotelReservation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_cash_payment_of_regular_invoice_goes_to_main_register(): void
    {
        $hotelRegister = $this->openRegister('bar');
        $mainRegister = $this->openRegister();

        $this->actingAs($this->user);
        $this->postPayment($this->makeInvoice())->assertStatus(201);

        $this->assertSame(1, CashMovement::where('cash_register_id', $mainRegister->id)->count());
        $this->assertSame(0, CashMovement::where('cash_register_id', $hotelRegister->id)->count());
    }

    public function test_cash_payment_of_reservation_invoice_goes_to_rooms_register(): void
    {
        $mainRegister = $this->openRegister();
        $roomsRegister = $this->openRegister('rooms');
        $invoice = $this->makeInvoice();
        HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->actingAs($this->user);
        $this->postPayment($invoice)->assertStatus(201);

        $this->assertSame(1, CashMovement::where('cash_register_id', $roomsRegister->id)->count());
        $this->assertSame(0, CashMovement::where('cash_register_id', $mainRegister->id)->count());
    }

    public function test_cash_payment_is_refused_when_target_register_is_closed(): void
    {
        $this->openRegister('bar');
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user);
        $this->postPayment($invoice)->assertStatus(422);

        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(0, CashMovement::count());
    }

    public function test_non_cash_payment_does_not_need_a_register(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user);
        $this->postPayment($invoice, 'bank_transfer')->assertStatus(201);

        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(0, CashMovement::count());
    }

    public function test_cash_invoice_validation_is_refused_without_open_register(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user);
        $this->postJson("/api/invoices/{$invoice->id}/validate")->assertStatus(422);

        $invoice->refresh();
        $this->assertFalse((bool) $invoice->is_validated);
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_main_register_movement_requires_full_access(): void
    {
        $register = $this->openRegister();
        $this->grantPermissions($this->user, ['sales']);

        $this->actingAs($this->user);
        $this->postMovement($register, 'income')->assertStatus(403);

        $this->assertSame(0, CashMovement::count());
    }

    public function test_finance_user_can_add_main_register_movement(): void
    {
        $register = $this->openRegister();
        $this->grantPermissions($this->user, ['finance']);

        $this->actingAs($this->user);
        $this->postMovement($register, 'income')->assertStatus(201);
    }

    public function test_expense_cannot_exceed_register_balance(): void
    {
        $register = $this->openRegister(null, 100);
        $this->grantPermissions($this->user, ['finance']);

        $this->actingAs($this->user);
        $this->postMovement($register, 'expense', 100.01)->assertStatus(422);
        $this->postMovement($register, 'expense', 100)->assertStatus(201);
    }

    public function test_order_taker_can_only_add_income_to_hotel_register(): void
    {
        $register = $this->openRegister('bar', 500);
        $this->grantPermissions($this->user, ['hotel_bar_order']);

        $this->actingAs($this->user);
        $this->postMovement($register, 'income')->assertStatus(201);
        $this->postMovement($register, 'expense')->assertStatus(403);
    }

    public function test_hotel_manager_can_add_expense_to_hotel_register(): void
    {
        $register = $this->openRegister('rooms', 500);
        $this->grantPermissions($this->user, ['hotel_rooms']);

        $this->actingAs($this->user);
        $this->postMovement($register, 'expense')->assertStatus(201);
    }

    public function test_user_without_hotel_permission_cannot_add_to_hotel_register(): void
    {
        $register = $this->openRegister('rooms');
        $this->grantPermissions($this->user, ['finance']);

        $this->actingAs($this->user);
        $this->postMovement($register, 'income')->assertStatus(403);
    }

    private function openRegister(?string $hotelSection = null, float $openingBalance = 0): CashRegister
    {
        return CashRegister::create([
            'company_id' => $this->company->id,
            'hotel_section' => $hotelSection,
            'opened_by' => $this->user->id,
            'opening_balance' => $openingBalance,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    private function makeInvoice(): Invoice
    {
        return Invoice::factory()->create([
            'company_id' => $this->company->id,
            'invoice_type' => 'FN',
            'payment_type' => '1',
            'invoice_total_amount' => 1000,
            'total_paid' => 0,
            'is_validated' => false,
            'is_cancelled' => false,
        ]);
    }

    private function postPayment(Invoice $invoice, string $method = 'cash')
    {
        return $this->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'payment_date' => now()->toDateString(),
            'payment_method' => $method,
        ]);
    }

    private function postMovement(CashRegister $register, string $type, float $amount = 50)
    {
        return $this->postJson("/api/cash-registers/{$register->id}/movements", [
            'type' => $type,
            'amount' => $amount,
            'description' => 'Test',
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function grantPermissions(User $user, array $permissions): void
    {
        $user->roles()->attach(Role::factory()->create(['permissions' => $permissions]));
        $user->load('roles');
    }
}
