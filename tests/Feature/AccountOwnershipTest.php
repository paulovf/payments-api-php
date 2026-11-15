<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountOwnershipTest extends TestCase
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
            'holder' => 'Minha Conta',
            'balance_in_cents' => 10000,
        ]);
    }

    public function test_user_can_list_only_own_accounts(): void
    {
        $otherUser = User::factory()->create();

        $otherUser->accounts()->create([
            'holder' => 'Conta de Outro Usuário',
            'balance_in_cents' => 20000,
        ]);

        $response = $this->getJson('/api/accounts');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'holder' => 'Minha Conta',
            ])
            ->assertJsonMissing([
                'holder' => 'Conta de Outro Usuário',
            ]);
    }

    public function test_user_can_create_account_owned_by_them(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->postJson('/api/accounts', [
            'holder' => 'Nova Conta',
            'user_id' => $otherUser->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('accounts', [
            'holder' => 'Nova Conta',
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseMissing('accounts', [
            'holder' => 'Nova Conta',
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_user_can_view_own_account(): void
    {
        $response = $this->getJson(
            "/api/accounts/{$this->account->id}"
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'holder' => 'Minha Conta',
            ]);
    }

    public function test_user_cannot_view_another_users_account(): void
    {
        $otherAccount = $this->createAccountForAnotherUser();

        $response = $this->getJson(
            "/api/accounts/{$otherAccount->id}"
        );

        $response->assertForbidden();
    }

    public function test_user_can_update_own_account(): void
    {
        $response = $this->putJson(
            "/api/accounts/{$this->account->id}",
            [
                'holder' => 'Conta Atualizada',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'holder' => 'Conta Atualizada',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'holder' => 'Conta Atualizada',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_cannot_update_another_users_account(): void
    {
        $otherAccount = $this->createAccountForAnotherUser();

        $response = $this->putJson(
            "/api/accounts/{$otherAccount->id}",
            [
                'holder' => 'Tentativa de Alteração',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $otherAccount->id,
            'holder' => 'Conta de Outro Usuário',
        ]);
    }

    public function test_user_can_delete_own_account_without_transactions(): void
    {
        $response = $this->deleteJson(
            "/api/accounts/{$this->account->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('accounts', [
            'id' => $this->account->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_account(): void
    {
        $otherAccount = $this->createAccountForAnotherUser();

        $response = $this->deleteJson(
            "/api/accounts/{$otherAccount->id}"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $otherAccount->id,
            'user_id' => $otherAccount->user_id,
        ]);
    }

    private function createAccountForAnotherUser(): Account
    {
        $otherUser = User::factory()->create();

        return $otherUser->accounts()->create([
            'holder' => 'Conta de Outro Usuário',
            'balance_in_cents' => 20000,
        ]);
    }
}