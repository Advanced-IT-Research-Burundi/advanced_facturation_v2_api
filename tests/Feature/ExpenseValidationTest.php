<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Depense;
use App\Models\DepenseCategory;
use App\Models\ExpenseFundMovement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected User $cashier;

    protected DepenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->roles()->attach(Role::factory()->create(['name' => 'admin']));
        $this->cashier = User::factory()->create(['company_id' => $this->company->id]);
        $this->category = DepenseCategory::factory()->create(['company_id' => $this->company->id]);
    }

    private function createPendingExpense(float $amount = 1000): Depense
    {
        $this->actingAs($this->cashier);

        $response = $this->postJson('/api/depenses', [
            'name' => 'Électricité',
            'montant' => $amount,
            'depense_category_id' => $this->category->id,
        ])->assertCreated();

        return Depense::findOrFail($response->json('data.id'));
    }

    private function fundExpenseRegister(float $amount = 100000): void
    {
        ExpenseFundMovement::create([
            'company_id' => $this->company->id,
            'type' => ExpenseFundMovement::TYPE_DEPOSIT,
            'amount' => $amount,
            'description' => 'Alimentation',
            'created_by' => $this->admin->id,
        ]);
    }

    private function expenseMovementsCount(): int
    {
        return ExpenseFundMovement::where('type', ExpenseFundMovement::TYPE_EXPENSE)->count();
    }

    public function test_new_expense_is_pending_and_moves_no_money_without_open_register(): void
    {
        $depense = $this->createPendingExpense();

        $this->assertSame('pending', $depense->status);
        $this->assertSame(0, CashMovement::count());
        $this->assertSame(0, ExpenseFundMovement::count());
    }

    public function test_admin_approval_debits_only_the_expense_fund(): void
    {
        $this->fundExpenseRegister(1500);
        $register = CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->cashier->id,
            'opening_balance' => 5000,
            'opened_at' => now(),
            'status' => 'open',
        ]);
        $depense = $this->createPendingExpense(1000);
        $balanceBefore = $register->calculateExpectedBalance();

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame($this->admin->id, $depense->fresh()->validated_by);
        $this->assertSame(500.0, ExpenseFundMovement::balanceForCompany($this->company->id));
        $this->assertSame(0, CashMovement::count());
        $this->assertSame($balanceBefore, $register->fresh()->calculateExpectedBalance());
    }

    public function test_approval_is_refused_when_fund_balance_is_insufficient(): void
    {
        $this->fundExpenseRegister(999.99);
        $depense = $this->createPendingExpense(1000);

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $depense->fresh()->status);
        $this->assertNull($depense->fresh()->validated_by);
        $this->assertSame(0, $this->expenseMovementsCount());
        $this->assertSame(999.99, ExpenseFundMovement::balanceForCompany($this->company->id));
    }

    public function test_approval_is_refused_when_fund_was_never_funded(): void
    {
        $depense = $this->createPendingExpense(1000);

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertUnprocessable();

        $this->assertSame('pending', $depense->fresh()->status);
    }

    public function test_approval_can_use_the_exact_balance_then_refuses_the_next_expense(): void
    {
        $this->fundExpenseRegister(1000);
        $first = $this->createPendingExpense(1000);
        $second = $this->createPendingExpense(1);

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$first->id}/approve")->assertOk();
        $this->assertSame(0.0, ExpenseFundMovement::balanceForCompany($this->company->id));

        $this->postJson("/api/depenses/{$second->id}/approve")->assertUnprocessable();
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_admin_can_reject_with_a_reason_and_no_money_moves(): void
    {
        $depense = $this->createPendingExpense();

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/reject", ['rejection_reason' => 'Pas de justificatif'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Pas de justificatif');

        $this->assertSame(0, ExpenseFundMovement::count());
    }

    public function test_non_admin_cannot_approve_or_reject(): void
    {
        $depense = $this->createPendingExpense();

        $this->postJson("/api/depenses/{$depense->id}/approve")->assertForbidden();
        $this->postJson("/api/depenses/{$depense->id}/reject")->assertForbidden();

        $financeUser = User::factory()->create(['company_id' => $this->company->id]);
        $financeUser->roles()->attach(Role::factory()->create(['name' => 'comptable', 'label' => 'Comptable', 'permissions' => ['finance']]));
        $this->actingAs($financeUser);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertForbidden();

        $this->assertSame('pending', $depense->fresh()->status);
    }

    public function test_expense_can_only_be_decided_once(): void
    {
        $this->fundExpenseRegister();
        $depense = $this->createPendingExpense();

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertOk();
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertUnprocessable();
        $this->postJson("/api/depenses/{$depense->id}/reject")->assertUnprocessable();

        $this->assertSame(1, $this->expenseMovementsCount());
    }

    public function test_only_pending_expense_can_be_edited_by_its_author_or_an_admin(): void
    {
        $this->fundExpenseRegister();
        $depense = $this->createPendingExpense();

        $otherCashier = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($otherCashier);
        $this->putJson("/api/depenses/{$depense->id}", ['montant' => 1])->assertForbidden();

        $this->actingAs($this->cashier);
        $this->putJson("/api/depenses/{$depense->id}", ['montant' => 2000])->assertOk();
        $this->assertSame(2000.0, $depense->fresh()->montant);
        $this->assertSame(0, CashMovement::count());

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertOk();
        $this->putJson("/api/depenses/{$depense->id}", ['montant' => 1])->assertUnprocessable();
    }

    public function test_deleting_an_approved_expense_is_admin_only_and_refunds_the_fund(): void
    {
        $this->fundExpenseRegister(1000);
        $depense = $this->createPendingExpense();

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertOk();

        $this->actingAs($this->cashier);
        $this->deleteJson("/api/depenses/{$depense->id}")->assertForbidden();

        $this->actingAs($this->admin);
        $this->deleteJson("/api/depenses/{$depense->id}")->assertOk();

        $this->assertSoftDeleted($depense);
        $this->assertSame(1000.0, ExpenseFundMovement::balanceForCompany($this->company->id));
    }

    public function test_author_can_delete_own_pending_expense(): void
    {
        $depense = $this->createPendingExpense();

        $this->deleteJson("/api/depenses/{$depense->id}")->assertOk();

        $this->assertSoftDeleted($depense);
    }

    public function test_restored_expense_goes_back_to_pending_and_active_one_cannot_be_restored(): void
    {
        $this->fundExpenseRegister();
        $depense = $this->createPendingExpense();

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertOk();
        $this->postJson("/api/depenses/{$depense->id}/restore")->assertUnprocessable();
        $this->assertSame(1, $this->expenseMovementsCount());

        $this->deleteJson("/api/depenses/{$depense->id}")->assertOk();
        $this->postJson("/api/depenses/{$depense->id}/restore")->assertOk();

        $this->assertSame('pending', $depense->fresh()->status);
        $this->assertSame(0, $this->expenseMovementsCount());
    }

    public function test_list_total_counts_only_approved_expenses(): void
    {
        $this->fundExpenseRegister();
        $approved = $this->createPendingExpense(1000);
        $this->createPendingExpense(500);

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$approved->id}/approve")->assertOk();

        $this->getJson('/api/depenses')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('total_approved', 1000);

        $this->getJson('/api/depenses?status=pending')->assertJsonPath('data.total', 1);
    }

    public function test_cash_balance_report_ignores_fund_pending_and_rejected_expenses(): void
    {
        $this->fundExpenseRegister();
        $fundExpense = $this->createPendingExpense(1000);
        $rejected = $this->createPendingExpense(300);
        $this->createPendingExpense(500);

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$fundExpense->id}/approve")->assertOk();
        $this->postJson("/api/depenses/{$rejected->id}/reject")->assertOk();

        Depense::factory()->create([
            'montant' => 70,
            'company_id' => $this->company->id,
            'depense_category_id' => $this->category->id,
            'user_id' => $this->admin->id,
        ]);

        $this->getJson('/api/reports/cash-balance?hotel_section=general&date_from='.today()->toDateString().'&date_to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.total_expenses', 70);
    }

    public function test_hotel_expense_keeps_immediate_register_debit(): void
    {
        CashRegister::create([
            'company_id' => $this->company->id,
            'hotel_section' => 'bar',
            'opened_by' => $this->cashier->id,
            'opening_balance' => 5000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->actingAs($this->cashier);
        $response = $this->postJson('/api/depenses', [
            'name' => 'Glace',
            'montant' => 1000,
            'hotel_section' => 'bar',
        ])->assertCreated();

        $this->assertSame('approved', Depense::findOrFail($response->json('data.id'))->status);
        $this->assertSame(1, CashMovement::where('type', 'expense')->count());
        $this->assertSame(0, ExpenseFundMovement::count());
    }

    public function test_only_admin_can_see_and_fund_the_expense_fund(): void
    {
        $this->actingAs($this->cashier);
        $this->getJson('/api/expense-fund')->assertForbidden();
        $this->postJson('/api/expense-fund/deposits', ['amount' => 100])->assertForbidden();

        $this->actingAs($this->admin);
        $this->postJson('/api/expense-fund/deposits', ['amount' => 3000])
            ->assertCreated()
            ->assertJsonPath('data.balance', 3000);
        $this->postJson('/api/expense-fund/deposits', ['amount' => 0])->assertUnprocessable();

        $this->getJson('/api/expense-fund')
            ->assertOk()
            ->assertJsonPath('data.balance', 3000)
            ->assertJsonPath('data.movements.total', 1);
    }

    public function test_fund_movement_shows_who_made_and_who_validated_the_expense(): void
    {
        $this->fundExpenseRegister();
        $depense = $this->createPendingExpense();

        $this->actingAs($this->admin);
        $this->postJson("/api/depenses/{$depense->id}/approve")->assertOk();
        $this->postJson('/api/expense-fund/deposits', ['amount' => 3000])->assertCreated();

        $response = $this->getJson('/api/expense-fund')->assertOk();
        $movements = collect($response->json('data.movements.data'));

        $expense = $movements->firstWhere('type', 'expense');
        $this->assertSame($this->cashier->name, $expense['depense']['user']['name']);
        $this->assertSame($this->admin->name, $expense['created_by']['name']);

        $deposit = $movements->firstWhere('type', 'deposit');
        $this->assertSame($this->admin->name, $deposit['created_by']['name']);

        $this->getJson('/api/depenses')
            ->assertJsonPath('data.data.0.user.name', $this->cashier->name)
            ->assertJsonPath('data.data.0.validated_by.name', $this->admin->name);
    }

    public function test_fund_is_isolated_between_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherAdmin->roles()->attach(Role::factory()->create(['name' => 'admin']));

        $this->actingAs($this->admin);
        $this->postJson('/api/expense-fund/deposits', ['amount' => 3000])->assertCreated();

        $this->actingAs($otherAdmin);
        $this->getJson('/api/expense-fund')
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.movements.total', 0);
    }
}
