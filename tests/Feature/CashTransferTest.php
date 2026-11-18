<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashTransfer;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashTransferTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $sender;

    protected User $recipient;

    protected CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->sender = User::factory()->create(['company_id' => $this->company->id]);
        $this->recipient = User::factory()->create(['company_id' => $this->company->id]);

        $this->register = CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->sender->id,
            'opening_balance' => 0,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        CashMovement::create([
            'cash_register_id' => $this->register->id,
            'type' => 'income',
            'amount' => 1000,
            'description' => 'Vente',
            'created_by' => $this->sender->id,
        ]);
    }

    public function test_transfer_moves_money_only_after_recipient_validation(): void
    {
        $this->actingAs($this->sender);
        $response = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 400,
        ]);
        $response->assertStatus(201)->assertJson(['success' => true]);

        $transferId = $response->json('data.id');
        $this->assertSame(0, CashMovement::where('type', 'adjustment')->count());

        $this->getJson("/api/cash-transfers/available/{$this->sender->id}")
            ->assertJsonPath('data.available', 600);

        $this->actingAs($this->recipient);
        $this->postJson("/api/cash-transfers/{$transferId}/accept")->assertOk();

        $this->assertSame('accepted', CashTransfer::find($transferId)->status);
        $this->assertEquals(-400, CashMovement::where('created_by', $this->sender->id)->where('type', 'adjustment')->sum('amount'));
        $this->assertEquals(400, CashMovement::where('created_by', $this->recipient->id)->where('type', 'adjustment')->sum('amount'));
        $this->assertEquals(1000, $this->register->fresh()->calculateExpectedBalance());
    }

    public function test_pending_transfer_is_deducted_from_sender_displayed_amount(): void
    {
        $this->actingAs($this->sender);
        $transferId = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 400,
        ])->json('data.id');

        $this->getJson('/api/cash-registers/daily-summary?hotel_section=null')
            ->assertJsonPath('data.user_summaries.0.pending_out', 400)
            ->assertJsonPath('data.user_summaries.0.net_amount', 600);

        $this->getJson('/api/cash-registers/current')
            ->assertJsonPath('data.summary.pending_out', 400)
            ->assertJsonPath('data.summary.expected_balance', 600);

        $this->assertEquals(1000, $this->register->fresh()->calculateExpectedBalance());

        $this->postJson("/api/cash-transfers/{$transferId}/cancel")->assertOk();

        $this->getJson('/api/cash-registers/daily-summary?hotel_section=null')
            ->assertJsonPath('data.user_summaries.0.pending_out', 0)
            ->assertJsonPath('data.user_summaries.0.net_amount', 1000);
    }

    public function test_cannot_transfer_more_than_available(): void
    {
        $this->actingAs($this->sender);

        $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 700,
        ])->assertStatus(201);

        $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 400,
        ])->assertStatus(422);
    }

    public function test_only_recipient_can_accept(): void
    {
        $this->actingAs($this->sender);
        $transferId = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
        ])->json('data.id');

        $this->postJson("/api/cash-transfers/{$transferId}/accept")->assertStatus(403);
    }

    public function test_full_access_user_cannot_transfer_from_another_users_cash(): void
    {
        $financeUser = User::factory()->create(['company_id' => $this->company->id]);
        $financeUser->roles()->attach(Role::factory()->create(['permissions' => ['finance']]));

        $this->actingAs($financeUser);

        $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
        ])->assertStatus(403);

        $this->assertSame(0, CashTransfer::count());
    }

    public function test_non_admin_cannot_transfer_from_another_users_cash(): void
    {
        $this->actingAs($this->recipient);

        $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
        ])->assertStatus(403);
    }

    public function test_yesterday_pending_transfer_cannot_be_accepted(): void
    {
        $transfer = CashTransfer::create([
            'company_id' => $this->company->id,
            'cash_register_id' => $this->register->id,
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
            'transfer_date' => now()->subDay(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->recipient);
        $this->postJson("/api/cash-transfers/{$transfer->id}/accept")->assertStatus(422);
    }

    public function test_sender_can_cancel_pending_transfer(): void
    {
        $this->actingAs($this->sender);
        $transferId = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
        ])->json('data.id');

        $this->postJson("/api/cash-transfers/{$transferId}/cancel")->assertOk();
        $this->assertSame('cancelled', CashTransfer::find($transferId)->status);
    }

    public function test_accepted_transfer_cannot_be_cancelled_or_rejected(): void
    {
        $this->actingAs($this->sender);
        $transferId = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
        ])->json('data.id');

        $this->actingAs($this->recipient);
        $this->postJson("/api/cash-transfers/{$transferId}/accept")->assertOk();
        $this->postJson("/api/cash-transfers/{$transferId}/reject")->assertStatus(422);

        $this->actingAs($this->sender);
        $this->postJson("/api/cash-transfers/{$transferId}/cancel")->assertStatus(422);

        $this->assertSame('accepted', CashTransfer::find($transferId)->status);
    }

    public function test_recipient_can_reject_pending_transfer(): void
    {
        $this->actingAs($this->sender);
        $transferId = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 100,
        ])->json('data.id');

        $this->actingAs($this->recipient);
        $this->postJson("/api/cash-transfers/{$transferId}/reject")->assertOk();

        $this->assertSame('rejected', CashTransfer::find($transferId)->status);
        $this->assertSame(0, CashMovement::where('type', 'adjustment')->count());
    }

    public function test_user_can_read_own_available_amount(): void
    {
        $this->actingAs($this->sender);

        $this->getJson("/api/cash-transfers/available/{$this->sender->id}")
            ->assertOk()
            ->assertJsonPath('data.available', 1000);
    }

    public function test_non_admin_cannot_read_another_users_available_amount(): void
    {
        $this->actingAs($this->recipient);

        $this->getJson("/api/cash-transfers/available/{$this->sender->id}")->assertStatus(403);
    }

    public function test_full_access_user_can_read_another_users_available_amount(): void
    {
        $financeUser = User::factory()->create(['company_id' => $this->company->id]);
        $financeUser->roles()->attach(Role::factory()->create(['permissions' => ['finance']]));

        $this->actingAs($financeUser);

        $this->getJson("/api/cash-transfers/available/{$this->sender->id}")
            ->assertOk()
            ->assertJsonPath('data.available', 1000);
    }

    public function test_accepted_transfer_is_shown_as_received_in_recipient_cash(): void
    {
        $this->actingAs($this->sender);
        $transferId = $this->postJson('/api/cash-transfers', [
            'from_user_id' => $this->sender->id,
            'to_user_id' => $this->recipient->id,
            'amount' => 400,
        ])->json('data.id');

        $this->actingAs($this->recipient);
        $this->postJson("/api/cash-transfers/{$transferId}/accept")->assertOk();

        $this->getJson('/api/cash-registers/current')
            ->assertJsonPath('data.summary.transfers_in', 400)
            ->assertJsonPath('data.summary.expected_balance', 400);

        $this->actingAs($this->sender);
        $this->getJson('/api/cash-registers/current')
            ->assertJsonPath('data.summary.transfers_out', 400)
            ->assertJsonPath('data.summary.expected_balance', 600);
    }

    public function test_history_lists_transfers_across_all_days_for_full_access_user(): void
    {
        $other = User::factory()->create(['company_id' => $this->company->id]);
        $this->createTransfer($this->sender, $this->recipient, 100, now()->subDays(10), 'accepted');
        $this->createTransfer($this->recipient, $this->sender, 200, now()->subDay(), 'rejected');
        $this->createTransfer($other, $this->recipient, 300, now(), 'pending');

        $financeUser = User::factory()->create(['company_id' => $this->company->id]);
        $financeUser->roles()->attach(Role::factory()->create(['permissions' => ['finance']]));
        $this->actingAs($financeUser);

        $this->getJson('/api/cash-transfers/history')
            ->assertOk()
            ->assertJsonPath('data.can_filter_users', true)
            ->assertJsonPath('data.transfers.total', 3)
            ->assertJsonPath('data.transfers.data.0.amount', '300.00')
            ->assertJsonPath('data.summary.accepted.total', 100);

        $this->getJson('/api/cash-transfers/history?start_date='.now()->subDays(2)->toDateString().'&status=rejected')
            ->assertJsonPath('data.transfers.total', 1)
            ->assertJsonPath('data.transfers.data.0.amount', '200.00');

        $this->getJson("/api/cash-transfers/history?user_id={$other->id}")
            ->assertJsonPath('data.transfers.total', 1);
    }

    public function test_history_only_shows_own_transfers_for_non_admin(): void
    {
        $other = User::factory()->create(['company_id' => $this->company->id]);
        $this->createTransfer($this->sender, $this->recipient, 100, now()->subDays(5), 'accepted');
        $this->createTransfer($other, $this->sender, 200, now(), 'pending');

        $this->actingAs($this->recipient);

        $this->getJson("/api/cash-transfers/history?user_id={$other->id}")
            ->assertOk()
            ->assertJsonPath('data.can_filter_users', false)
            ->assertJsonPath('data.transfers.total', 1)
            ->assertJsonPath('data.transfers.data.0.amount', '100.00');
    }

    private function createTransfer(User $from, User $to, float $amount, $date, string $status): CashTransfer
    {
        return CashTransfer::create([
            'company_id' => $this->company->id,
            'cash_register_id' => $this->register->id,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'amount' => $amount,
            'transfer_date' => $date,
            'status' => $status,
        ]);
    }
}
