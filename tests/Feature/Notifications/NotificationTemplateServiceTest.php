<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateServiceTest extends TestCase
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

    public function test_clinic_owner_can_create_tenant_template_override(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $template = app(NotificationTemplateService::class)->create([
            'event_key' => 'appointment.confirmation',
            'channel' => 'sms',
            'name' => 'Clinic override SMS',
            'body' => 'Hi {{patient_name}} - custom message',
        ]);

        $this->assertSame($tenant->id, $template->tenant_id);
        $this->assertTrue($template->is_active);
    }

    public function test_tenant_cannot_create_global_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(NotificationTemplateService::class)->create([
            'event_key' => 'appointment.confirmation',
            'channel' => 'sms',
            'name' => 'Global',
            'body' => 'x',
            'is_global' => true,
        ]);
    }

    public function test_duplicate_event_channel_in_same_scope_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(NotificationTemplateService::class);
        $service->create([
            'event_key' => 'appointment.reminder',
            'channel' => 'sms',
            'name' => 'First',
            'body' => 'A',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->create([
            'event_key' => 'appointment.reminder',
            'channel' => 'sms',
            'name' => 'Second',
            'body' => 'B',
        ]);
    }

    public function test_tenant_cannot_modify_global_template(): void
    {
        $tenant = Tenant::factory()->create();

        // Create a global template in Super-Admin (no-tenant) context so the
        // BelongsToTenant creating hook does not stamp a tenant_id.
        $ctx = app(TenantContext::class);
        $ctx->forget();
        $global = NotificationTemplate::create([
            'event_key' => 'appointment.confirmation',
            'channel' => 'email',
            'name' => 'Global',
            'body' => 'x',
            'is_active' => true,
        ]);
        $this->assertNull($global->tenant_id);

        // Now act as a tenant user.
        $ctx->set($tenant->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(NotificationTemplateService::class)->update(
            NotificationTemplate::withoutGlobalScope('tenant')->find($global->id),
            ['name' => 'Hacked']
        );
    }

    public function test_seeder_creates_default_global_templates(): void
    {
        $this->artisan('db:seed', ['--class' => 'NotificationTemplateSeeder', '--force' => true]);

        $count = NotificationTemplate::withoutGlobalScope('tenant')->whereNull('tenant_id')->count();
        $this->assertGreaterThanOrEqual(10, $count);

        $this->assertDatabaseHas('notification_templates', [
            'tenant_id' => null,
            'event_key' => 'appointment.confirmation',
            'channel' => 'sms',
        ]);
    }

    public function test_api_clinic_owner_can_list_templates_including_global(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $globalTemplate = new NotificationTemplate();
        $globalTemplate->forceFill([
            'tenant_id' => null,
            'event_key' => 'appointment.confirmation',
            'channel' => 'sms',
            'name' => 'Global SMS',
            'body' => 'x',
            'is_active' => true,
        ])->save();
        NotificationTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'appointment.reminder',
            'channel' => 'sms',
        ]);

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/notification-templates?include_global=1')
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function test_api_doctor_cannot_manage_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/notification-templates')
            ->assertStatus(403);
    }

    public function test_api_clinic_owner_can_create_tenant_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/notification-templates', [
                'event_key' => 'appointment.confirmation',
                'channel' => 'sms',
                'name' => 'Clinic SMS',
                'body' => 'Hi {{patient_name}}',
            ])
            ->assertStatus(201)
            ->assertJsonPath('event_key', 'appointment.confirmation')
            ->assertJsonPath('is_global', false);
    }
}
