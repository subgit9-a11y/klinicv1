<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\Billing\CashRegisterScreen;
use App\Livewire\Billing\ExpenseTracker;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingScreensTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    // --- CashRegisterScreen ---

    public function test_open_and_close_with_variance(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CashRegisterScreen::class)
            ->set('open_name', 'Front Desk')
            ->set('open_balance_rupees', 1000)
            ->call('open');

        $register = \App\Models\CashRegister::where('name', 'Front Desk')->sole();
        $this->assertSame('OPEN', $register->status);

        Livewire::actingAs($this->owner)
            ->test(CashRegisterScreen::class)
            ->call('select', $register->id)
            ->set('actual_balance_rupees', 990)
            ->call('close', $register->id);

        $fresh = $register->fresh();
        $this->assertSame('CLOSED', $fresh->status);
        $this->assertSame(99000, $fresh->actual_balance_cents);
        $this->assertSame(-1000, $fresh->variance_cents); // counted ₹990 − expected ₹1000
    }

    public function test_double_open_flashes_friendly_error(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CashRegisterScreen::class)
            ->set('open_name', 'R1')
            ->call('open');

        Livewire::actingAs($this->owner)
            ->test(CashRegisterScreen::class)
            ->set('open_name', 'R2')
            ->call('open')
            ->assertSee('already open');
    }

    public function test_post_adjustment(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CashRegisterScreen::class)
            ->call('open');
        $register = \App\Models\CashRegister::first();

        Livewire::actingAs($this->owner)
            ->test(CashRegisterScreen::class)
            ->call('select', $register->id)
            ->set('adjust_type', 'DEBIT')
            ->set('adjust_amount_rupees', 50)
            ->set('adjust_reason', 'Petty cash out')
            ->call('postAdjustment', $register->id);

        $this->assertDatabaseHas('cash_register_entries', [
            'cash_register_id' => $register->id,
            'type' => 'DEBIT',
            'amount_cents' => 5000,
        ]);
    }

    // --- ExpenseTracker ---

    public function test_expense_guard(): void
    {
        $nurse = User::factory()->forTenant($this->tenant)->role('NURSE')->create();

        Livewire::actingAs($nurse)->test(ExpenseTracker::class)->assertStatus(403);
    }

    public function test_add_expense_records_cents_and_lists(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ExpenseTracker::class)
            ->set('description', 'Electricity bill')
            ->set('category', 'UTILITIES')
            ->set('payment_method', 'UPI')
            ->set('amount_rupees', 1450)
            ->call('addExpense')
            ->assertSee('Electricity bill');

        $this->assertDatabaseHas('expenses', [
            'description' => 'Electricity bill',
            'category' => 'UTILITIES',
            'amount_cents' => 145000,
        ]);
    }
}
