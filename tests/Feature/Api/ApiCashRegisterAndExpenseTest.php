<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCashRegisterAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function tokenHeader(User $user): array
    {
        $issued = app(TokenService::class)->create($user, 'test', ['*']);

        return ['Authorization' => 'Bearer '.$issued['token']];
    }

    public function test_receptionist_can_open_and_close_cash_register(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $open = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/cash-registers', [
                'name' => 'Front Desk',
                'opening_balance_cents' => 5000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.opening_balance', 50);

        $registerId = $open->json('data.id');

        $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/cash-registers/{$registerId}/close")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_doctor_cannot_open_cash_register_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']); // no cash_register.manage

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/cash-registers', ['opening_balance_cents' => 1000])
            ->assertStatus(403);
    }

    public function test_cash_register_entries_listed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $open = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/cash-registers', ['opening_balance_cents' => 5000])
            ->assertStatus(201);

        $registerId = $open->json('data.id');

        $this->withHeaders($this->tokenHeader($user))
            ->getJson("/api/v1/cash-registers/{$registerId}/entries")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data') // the opening float credit
            ->assertJsonPath('data.0.type', 'CREDIT');
    }

    public function test_clinic_owner_can_record_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/expenses', [
                'category' => 'RENT',
                'description' => 'August rent',
                'amount_cents' => 2500000,
                'payment_method' => 'BANK_TRANSFER',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.category', 'RENT')
            ->assertJsonPath('data.amount', 25000);
    }

    public function test_expense_validation_requires_description_and_amount(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/expenses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description', 'amount_cents']);
    }

    public function test_expense_list_filtered_by_category(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        Expense::factory()->create(['tenant_id' => $tenant->id, 'category' => 'RENT']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'category' => 'SUPPLIES']);

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/expenses?category=RENT')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'RENT');
    }

    public function test_expense_linked_to_open_register_posts_debit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $open = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/cash-registers', ['opening_balance_cents' => 10000])
            ->assertStatus(201);
        $registerId = $open->json('data.id');

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/expenses', [
                'description' => 'Stationery',
                'amount_cents' => 1500,
                'payment_method' => 'CASH',
                'cash_register_id' => $registerId,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('cash_register_entries', [
            'cash_register_id' => $registerId,
            'type' => 'DEBIT',
            'amount_cents' => 1500,
        ]);
    }
}
