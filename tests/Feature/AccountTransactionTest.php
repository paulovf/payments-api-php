<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Sanctum::actingAs($this->user);

        $this->account = $this->user->accounts()->create([
            'holder' => 'Conta de Teste',
            'balance_in_cents' => 10000,
        ]);
    }

    public function test_credit_increases_account_balance(): void
    {
        $response = $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            [
                'amount_in_cents' => 5000,
                'type' => 'credit',
                'description' => 'Depósito',
            ],
            ['Idempotency-Key' => fake()->uuid()]
        );

        $response->assertCreated();

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'balance_in_cents' => 15000,
        ]);
    }

    public function test_debit_decreases_account_balance(): void
    {
        $response = $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            [
                'amount_in_cents' => 2500,
                'type' => 'debit',
                'description' => 'Pagamento',
            ],
            ['Idempotency-Key' => fake()->uuid()]
        );

        $response->assertCreated();

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'balance_in_cents' => 7500,
        ]);
    }

    public function test_debit_with_insufficient_balance_is_rejected(): void
    {
        $response = $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            [
                'amount_in_cents' => 15000,
                'type' => 'debit',
                'description' => 'Pagamento acima do saldo',
            ],
            ['Idempotency-Key' => fake()->uuid()]
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'balance_in_cents' => 10000,
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_repeated_request_does_not_duplicate_transaction(): void
    {
        $key = fake()->uuid();

        $payload = [
            'amount_in_cents' => 2500,
            'type' => 'debit',
            'description' => 'Pagamento',
        ];

        $firstResponse = $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            $payload,
            ['Idempotency-Key' => $key]
        );

        $firstResponse->assertCreated();

        $secondResponse = $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            $payload,
            ['Idempotency-Key' => $key]
        );

        $secondResponse
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('transactions', 1);

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'balance_in_cents' => 7500,
        ]);
    }

    public function test_idempotency_key_cannot_be_reused_with_different_data(): void
    {
        $key = fake()->uuid();

        $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            [
                'amount_in_cents' => 2000,
                'type' => 'debit',
                'description' => 'Pagamento A',
            ],
            ['Idempotency-Key' => $key]
        )->assertCreated();

        $this->postJson(
            "/api/accounts/{$this->account->id}/transactions",
            [
                'amount_in_cents' => 3000,
                'type' => 'debit',
                'description' => 'Pagamento B',
            ],
            ['Idempotency-Key' => $key]
        )->assertConflict();

        $this->assertDatabaseCount('transactions', 1);

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'balance_in_cents' => 8000,
        ]);
    }

    public function test_user_can_view_own_transactions(): void
    {
        $response = $this->getJson(
            "/api/accounts/{$this->account->id}/transactions"
        );

        $response->assertOk();
    }

    public function test_user_cannot_view_another_users_transactions(): void
    {
        $otherUser = User::factory()->create();

        $otherAccount = $otherUser->accounts()->create([
            'holder' => 'Conta de Outro Usuário',
            'balance_in_cents' => 10000,
        ]);

        $response = $this->getJson(
            "/api/accounts/{$otherAccount->id}/transactions"
        );

        $response->assertForbidden();
    }

    public function test_user_cannot_create_transaction_on_another_users_account(): void
    {
        $otherUser = User::factory()->create();

        $otherAccount = $otherUser->accounts()->create([
            'holder' => 'Conta de Outro Usuário',
            'balance_in_cents' => 10000,
        ]);

        $response = $this->postJson(
            "/api/accounts/{$otherAccount->id}/transactions",
            [
                'amount_in_cents' => 5000,
                'type' => 'debit',
                'description' => 'Tentativa não autorizada',
            ],
            ['Idempotency-Key' => fake()->uuid()]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $otherAccount->id,
            'balance_in_cents' => 10000,
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }
}