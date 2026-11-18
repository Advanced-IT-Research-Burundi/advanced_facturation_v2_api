<?php

namespace Tests\Feature;

use App\Models\BankTransfer;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $cashier;

    protected User $admin;

    protected CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->cashier = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->roles()->attach(Role::factory()->create(['name' => 'admin', 'permissions' => []]));

        $this->register = CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->admin->id,
            'opening_balance' => 500,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        CashMovement::create([
            'cash_register_id' => $this->register->id,
            'type' => 'income',
            'amount' => 1000,
            'description' => 'Vente',
            'created_by' => $this->cashier->id,
        ]);
    }

    public function test_user_cash_leaves_register_only_after_admin_validation(): void
    {
        $this->actingAs($this->cashier);
        $transferId = $this->postJson('/api/bank-transfers', ['source' => 'user_cash', 'amount' => 400, 'reference' => 'BRD-1'])
            ->assertStatus(201)
            ->json('data.id');

        $this->assertSame(0, CashMovement::where('reference', 'BNK-'.$transferId)->count());
        $this->getJson('/api/bank-transfers')->assertOk()->assertJsonPath('data.available.user_cash', 600);

        $this->postJson("/api/bank-transfers/{$transferId}/accept")->assertStatus(403);

        $this->actingAs($this->admin);
        $this->postJson("/api/bank-transfers/{$transferId}/accept")->assertOk();

        $movement = CashMovement::where('reference', 'BNK-'.$transferId)->firstOrFail();
        $this->assertEquals(-400, $movement->amount);
        $this->assertSame($this->cashier->id, $movement->created_by);
        $this->assertSame(600.0, CashMovement::userDailyBalance($this->company->id, $this->cashier->id));

        $this->getJson('/api/bank-transfers')
            ->assertOk()
            ->assertJsonPath('data.summary.balance', 400)
            ->assertJsonPath('data.summary.by_source.user_cash', 400);
    }

    public function test_user_cash_transfer_cannot_exceed_available_amount(): void
    {
        $this->actingAs($this->cashier);
        $this->postJson('/api/bank-transfers', ['source' => 'user_cash', 'amount' => 700])->assertStatus(201);
        $this->postJson('/api/bank-transfers', ['source' => 'user_cash', 'amount' => 400])->assertStatus(422);
        $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->cashier->id,
            'to_user_id' => $this->admin->id,
            'amount' => 400,
        ])->assertStatus(422);
    }

    public function test_main_register_and_electronic_sources_require_full_access(): void
    {
        $this->actingAs($this->cashier);
        $this->postJson('/api/bank-transfers', ['source' => 'main_register', 'amount' => 100])->assertStatus(403);
        $this->postJson('/api/bank-transfers', ['source' => 'electronic', 'amount' => 100])->assertStatus(403);
    }

    public function test_main_register_transfer_is_limited_to_register_balance(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/api/bank-transfers', ['source' => 'main_register', 'amount' => 1600])->assertStatus(422);
        $transferId = $this->postJson('/api/bank-transfers', ['source' => 'main_register', 'amount' => 1500])
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson("/api/bank-transfers/{$transferId}/accept")->assertOk();

        $this->assertEquals(0, $this->register->fresh()->calculateExpectedBalance());
    }

    public function test_electronic_transfer_is_limited_to_electronic_sales_and_does_not_touch_register(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'user_id' => $this->cashier->id,
            'invoice_type' => 'FN',
            'invoice_date' => now(),
            'payment_type' => '4',
            'payment_method_id' => null,
            'invoice_total_amount' => 3000,
            'is_validated' => true,
            'is_cancelled' => false,
        ]);

        $this->actingAs($this->admin);
        $this->postJson('/api/bank-transfers', ['source' => 'electronic', 'amount' => 3500])->assertStatus(422);
        $transferId = $this->postJson('/api/bank-transfers', ['source' => 'electronic', 'amount' => 2000])
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson("/api/bank-transfers/{$transferId}/accept")->assertOk();

        $this->assertSame(0, CashMovement::where('reference', 'BNK-'.$transferId)->count());
        $this->getJson('/api/bank-transfers')
            ->assertOk()
            ->assertJsonPath('data.available.electronic', 1000)
            ->assertJsonPath('data.summary.balance', 2000);
    }

    public function test_rejected_and_cancelled_transfers_do_not_move_money(): void
    {
        $this->actingAs($this->cashier);
        $first = $this->postJson('/api/bank-transfers', ['source' => 'user_cash', 'amount' => 300])->json('data.id');
        $second = $this->postJson('/api/bank-transfers', ['source' => 'user_cash', 'amount' => 200])->json('data.id');

        $this->postJson("/api/bank-transfers/{$second}/cancel")->assertOk();

        $this->actingAs($this->admin);
        $this->postJson("/api/bank-transfers/{$first}/reject")->assertOk();
        $this->postJson("/api/bank-transfers/{$first}/accept")->assertStatus(422);

        $this->assertSame(0, CashMovement::where('reference', 'like', 'BNK-%')->count());
        $this->assertSame(['rejected', 'cancelled'], BankTransfer::orderBy('id')->pluck('status')->all());
    }

    public function test_pending_user_cash_transfer_expires_after_its_day(): void
    {
        $transfer = BankTransfer::create([
            'company_id' => $this->company->id,
            'source' => 'user_cash',
            'from_user_id' => $this->cashier->id,
            'cash_register_id' => $this->register->id,
            'created_by' => $this->cashier->id,
            'amount' => 100,
            'transfer_date' => now()->subDay(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);
        $this->postJson("/api/bank-transfers/{$transfer->id}/accept")->assertStatus(422);
    }

    public function test_cashier_only_sees_own_transfers(): void
    {
        $this->actingAs($this->cashier);
        $this->postJson('/api/bank-transfers', ['source' => 'user_cash', 'amount' => 100])->assertStatus(201);

        $this->actingAs($this->admin);
        $this->postJson('/api/bank-transfers', ['source' => 'main_register', 'amount' => 100])->assertStatus(201);

        $this->actingAs($this->cashier);
        $this->getJson('/api/bank-transfers')
            ->assertOk()
            ->assertJsonPath('data.transfers.total', 1)
            ->assertJsonPath('data.summary.balance', null);
    }
}
