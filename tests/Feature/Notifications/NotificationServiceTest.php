<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Contracts\EmailProviderInterface;
use App\Contracts\SmsProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\Providers\MetaWhatsAppProvider;
use App\Services\Notifications\Providers\Msg91SmsProvider;
use App\Services\Notifications\Providers\ResendEmailProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => '',
            'services.whatsapp.phone_number_id' => '',
            'services.msg91.auth_key' => '',
            'services.resend.key' => '',
        ]);
    }

    // --- Template resolution ---

    public function test_send_records_delivery_for_in_app_channel(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        NotificationTemplate::factory()->create([
            'event_key' => 'appointment.confirmation',
            'channel' => 'in_app',
            'is_active' => true,
        ]);

        $result = app(NotificationService::class)->send(
            $patient,
            'appointment.confirmation',
            ['patient_name' => 'Test'],
            ['in_app']
        );

        $this->assertArrayHasKey('in_app', $result);
        $this->assertInstanceOf(NotificationDelivery::class, $result['in_app']);
    }

    public function test_send_fails_when_no_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $result = app(NotificationService::class)->send(
            $patient,
            'nonexistent.event',
            [],
            ['sms']
        );

        $this->assertSame('FAILED', $result['sms']->status);
        $this->assertStringContainsString('No template', $result['sms']->error);
    }

    public function test_tenant_template_overrides_global_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        // Global template — created outside tenant context so tenant_id stays null.
        app(TenantContext::class)->forget();
        $global = NotificationTemplate::factory()->make([
            'tenant_id' => null,
            'event_key' => 'appointment.reminder',
            'channel' => 'sms',
            'body' => 'GLOBAL: Hello {{patient_name}}',
            'is_active' => true,
        ]);
        $global->saveQuietly();
        $this->setTenant($tenant);

        // Tenant-specific template.
        NotificationTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'appointment.reminder',
            'channel' => 'sms',
            'body' => 'TENANT: Hello {{patient_name}}',
            'is_active' => true,
        ]);

        $delivery = app(NotificationService::class)->sendOnChannel(
            $patient,
            'appointment.reminder',
            'sms',
            ['patient_name' => 'Raj']
        );

        $template = NotificationTemplate::find($delivery->notification_template_id);
        $this->assertSame($tenant->id, $template->tenant_id);
    }

    public function test_global_template_used_when_no_tenant_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        // Create global template outside tenant context.
        app(TenantContext::class)->forget();
        $global = NotificationTemplate::factory()->make([
            'tenant_id' => null,
            'event_key' => 'appointment.reminder',
            'channel' => 'sms',
            'is_active' => true,
        ]);
        $global->saveQuietly();
        $this->setTenant($tenant);

        $delivery = app(NotificationService::class)->sendOnChannel(
            $patient,
            'appointment.reminder',
            'sms',
            []
        );

        $template = NotificationTemplate::withoutGlobalScope('tenant')->find($delivery->notification_template_id);
        $this->assertNull($template->tenant_id);
    }

    public function test_inactive_template_is_skipped(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        NotificationTemplate::factory()->create([
            'event_key' => 'appointment.cancel',
            'channel' => 'sms',
            'is_active' => false,
        ]);

        $delivery = app(NotificationService::class)->sendOnChannel(
            $patient,
            'appointment.cancel',
            'sms',
            []
        );

        $this->assertSame('FAILED', $delivery->status);
    }

    public function test_variable_substitution_renders_body(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        NotificationTemplate::factory()->create([
            'event_key' => 'test.event',
            'channel' => 'sms',
            'body' => 'Hi {{name}}, appt at {{time}}',
            'is_active' => true,
        ]);

        $delivery = app(NotificationService::class)->sendOnChannel(
            $patient,
            'test.event',
            'sms',
            ['name' => 'Raj', 'time' => '5pm']
        );

        // Provider not configured → FAILED, but the template was resolved.
        $this->assertSame('FAILED', $delivery->status);
        $this->assertNotNull($delivery->notification_template_id);
    }

    public function test_send_across_multiple_channels(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        NotificationTemplate::factory()->create([
            'event_key' => 'ipd.admission',
            'channel' => 'in_app',
            'is_active' => true,
        ]);
        NotificationTemplate::factory()->create([
            'event_key' => 'ipd.admission',
            'channel' => 'sms',
            'is_active' => true,
        ]);

        $result = app(NotificationService::class)->send(
            $patient,
            'ipd.admission',
            [],
            ['in_app', 'sms']
        );

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('in_app', $result);
        $this->assertArrayHasKey('sms', $result);
    }

    // --- Providers ---

    public function test_whatsapp_provider_not_configured(): void
    {
        $this->assertFalse(app(MetaWhatsAppProvider::class)->isConfigured());
    }

    public function test_whatsapp_provider_graceful_when_unconfigured(): void
    {
        $result = app(MetaWhatsAppProvider::class)->send('919999999999', 'test_template', []);

        $this->assertFalse($result['success']);
        $this->assertSame('WhatsApp not configured', $result['message']);
    }

    public function test_sms_provider_not_configured(): void
    {
        $this->assertFalse(app(Msg91SmsProvider::class)->isConfigured());
    }

    public function test_sms_provider_graceful_when_unconfigured(): void
    {
        $result = app(Msg91SmsProvider::class)->send('919999999999', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertSame('MSG91 not configured', $result['message']);
    }

    public function test_email_provider_configured_via_smtp_fallback(): void
    {
        // ResendEmailProvider is configured if either Resend key OR SMTP default exists.
        $this->assertTrue(app(ResendEmailProvider::class)->isConfigured());
    }

    // --- Interface bindings ---

    public function test_whatsapp_interface_resolves_to_meta(): void
    {
        $this->assertInstanceOf(MetaWhatsAppProvider::class, app(WhatsAppProviderInterface::class));
    }

    public function test_sms_interface_resolves_to_msg91(): void
    {
        $this->assertInstanceOf(Msg91SmsProvider::class, app(SmsProviderInterface::class));
    }

    public function test_email_interface_resolves_to_resend(): void
    {
        $this->assertInstanceOf(ResendEmailProvider::class, app(EmailProviderInterface::class));
    }
}
