<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\Settings\ClinicSettings;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Settings\ClinicSettingsService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant->id);
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
    }

    public function test_defaults_returned_when_nothing_saved(): void
    {
        $settings = app(ClinicSettingsService::class);

        $types = $settings->appointmentTypes();

        $this->assertNotEmpty($types);
        $this->assertSame('CONSULTATION', $types[0]['key']);
        $this->assertSame(49900, $settings->onlineBookingFeeCents());
        $this->assertSame(30, $settings->onlineBookingDurationMinutes());
    }

    public function test_save_appointment_types_roundtrips(): void
    {
        $settings = app(ClinicSettingsService::class);

        $settings->saveAppointmentTypes([
            ['key' => 'CONSULTATION', 'label' => 'Consultation', 'duration_minutes' => 20],
            ['key' => 'REVIEW', 'label' => 'Review', 'duration_minutes' => 10],
        ]);

        $types = $settings->appointmentTypes();
        $this->assertCount(2, $types);
        $this->assertSame(20, $types[0]['duration_minutes']);
        $this->assertSame('REVIEW', $types[1]['key']);
    }

    public function test_save_appointment_types_rejects_duplicates_and_bad_key(): void
    {
        $settings = app(ClinicSettingsService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $settings->saveAppointmentTypes([
            ['key' => 'CONSULTATION', 'label' => 'One', 'duration_minutes' => 20],
            ['key' => 'CONSULTATION', 'label' => 'Two', 'duration_minutes' => 20],
        ]);
    }

    public function test_online_booking_override_affects_booking_service(): void
    {
        app(ClinicSettingsService::class)->saveOnlineBooking(12300, 45);

        // OnlineBookingService constructor-injects ClinicSettingsService for fee/duration.
        $settings = app(ClinicSettingsService::class);
        $this->assertSame(12300, $settings->onlineBookingFeeCents());
        $this->assertSame(45, $settings->onlineBookingDurationMinutes());
    }

    public function test_owner_can_save_types_via_livewire(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSettings::class)
            ->call('addType')
            ->set('types.3.key', 'EMERGENCY')
            ->set('types.3.label', 'Emergency visit')
            ->set('types.3.duration_minutes', 60)
            ->call('saveTypes')
            ->assertHasNoErrors();

        $stored = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('key', ClinicSettingsService::KEY_APPOINTMENT_TYPES)
            ->value('value');

        $decoded = json_decode($stored, true);
        $this->assertCount(4, $decoded);
        $this->assertSame('EMERGENCY', $decoded[3]['key']);
    }

    public function test_owner_can_save_online_booking_via_livewire(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSettings::class)
            ->set('online_fee_rupees', 250)
            ->set('online_duration_minutes', 45)
            ->call('saveOnlineBooking')
            ->assertHasNoErrors();

        $settings = app(ClinicSettingsService::class);
        $this->assertSame(25000, $settings->onlineBookingFeeCents());
        $this->assertSame(45, $settings->onlineBookingDurationMinutes());
    }

    public function test_receptionist_gets_403(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        Livewire::actingAs($receptionist)
            ->test(ClinicSettings::class)
            ->assertForbidden();
    }

    public function test_tenant_settings_are_isolated(): void
    {
        app(ClinicSettingsService::class)->saveOnlineBooking(11100, 15);

        $other = Tenant::factory()->create();
        app(TenantContext::class)->set($other->id);

        $settings = app(ClinicSettingsService::class);
        $this->assertSame(49900, $settings->onlineBookingFeeCents($other->id));
    }
}
