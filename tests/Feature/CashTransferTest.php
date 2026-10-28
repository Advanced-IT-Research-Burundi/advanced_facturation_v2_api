<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashTransfer;
use App\Models\Company;
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
}
