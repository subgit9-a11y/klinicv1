<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\Notifications\TemplateManager;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateManagerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed global templates BEFORE setting tenant context, otherwise the
        // BelongsToTenant creating hook stamps the tenant onto global rows.
        $this->seed(\Database\Seeders\NotificationTemplateSeeder::class);

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant->id);
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
    }

    public function test_owner_sees_global_and_tenant_templates(): void
    {
        NotificationTemplate::create([
            'tenant_id' => $this->tenant->id,
            'event_key' => 'custom.event',
            'channel' => 'in_app',
            'name' => 'Clinic custom',
            'body' => 'Hello {patient_name}',
        ]);

        Livewire::actingAs($this->owner)
            ->test(TemplateManager::class)
            ->assertSee('appointment.confirmation')
            ->assertSee('Clinic custom');
    }

    public function test_owner_creates_tenant_template(): void
    {
        Livewire::actingAs($this->owner)
            ->test(TemplateManager::class)
            ->call('startCreate')
            ->set('event_key', 'payment.received')
            ->set('channel', 'sms')
            ->set('name', 'Payment received SMS (clinic)')
            ->set('body', 'Dear {patient_name}, received {amount}.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_templates', [
            'tenant_id' => $this->tenant->id,
            'event_key' => 'payment.received',
            'channel' => 'sms',
            'name' => 'Payment received SMS (clinic)',
        ]);
    }

    public function test_owner_edits_own_template(): void
    {
        $template = NotificationTemplate::create([
            'tenant_id' => $this->tenant->id,
            'event_key' => 'custom.event',
            'channel' => 'in_app',
            'name' => 'Original',
            'body' => 'Body',
        ]);

        Livewire::actingAs($this->owner)
            ->test(TemplateManager::class)
            ->call('startEdit', $template->id)
            ->set('name', 'Renamed')
            ->set('body', 'Updated body')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Renamed', $template->fresh()->name);
        $this->assertSame('Updated body', $template->fresh()->body);
    }

    public function test_owner_cannot_edit_global_template(): void
    {
        $global = NotificationTemplate::withoutGlobalScopes()->whereNull('tenant_id')->firstOrFail();

        Livewire::actingAs($this->owner)
            ->test(TemplateManager::class)
            ->call('startEdit', $global->id)
            ->assertForbidden();
    }

    public function test_owner_deletes_own_template(): void
    {
        $template = NotificationTemplate::create([
            'tenant_id' => $this->tenant->id,
            'event_key' => 'custom.event',
            'channel' => 'in_app',
            'name' => 'To delete',
            'body' => 'Body',
        ]);

        Livewire::actingAs($this->owner)
            ->test(TemplateManager::class)
            ->call('remove', $template->id);

        $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
    }

    public function test_owner_cannot_delete_global_template(): void
    {
        $global = NotificationTemplate::withoutGlobalScopes()->whereNull('tenant_id')->firstOrFail();

        Livewire::actingAs($this->owner)
            ->test(TemplateManager::class)
            ->call('remove', $global->id)
            ->assertHasErrors(['template']);

        $this->assertDatabaseHas('notification_templates', ['id' => $global->id]);
    }

    public function test_receptionist_gets_403(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        Livewire::actingAs($receptionist)
            ->test(TemplateManager::class)
            ->assertForbidden();
    }
}
