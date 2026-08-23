<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\IntegrationStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_integration_status(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IntegrationStatus::class)
            ->assertStatus(200)
            ->assertSee('Cashfree Payments')
            ->assertSee('Gemini AI')
            ->assertSee('WhatsApp (Meta)')
            ->assertSee('Storage (S3)');
    }

    public function test_unconfigured_providers_report_not_configured(): void
    {
        config(['services.cashfree.app_id' => null, 'services.cashfree.secret_key' => null]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IntegrationStatus::class)
            ->assertSee('Not configured');
    }

    public function test_non_super_admin_gets_403(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        Livewire::actingAs($user)
            ->test(IntegrationStatus::class)
            ->assertStatus(403);
    }

    public function test_save_account_stores_and_masks(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IntegrationStatus::class)
            ->set('accountProvider', 'whatsapp')
            ->set('credentials', ['api_token' => 'EAAtoken999', 'phone_number_id' => '12345'])
            ->call('saveAccount');

        // Re-render: masked preview only, never plaintext.
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IntegrationStatus::class)
            ->assertSee('whatsapp')
            ->assertSee('••••n999')
            ->assertDontSee('EAAtoken999');
    }

    public function test_save_account_validation(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IntegrationStatus::class)
            ->set('accountProvider', 'unknown')
            ->call('saveAccount')
            ->assertHasErrors('accountProvider');
    }

    public function test_toggle_and_delete_account(): void
    {
        $service = new \App\Services\Integrations\IntegrationAccountService;
        $account = $service->upsert('resend', ['api_key' => 're_abc']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IntegrationStatus::class)
            ->call('toggleAccount', $account->id)
            ->call('deleteAccount', $account->id);

        $this->assertDatabaseMissing('integration_accounts', ['id' => $account->id]);
    }
}
