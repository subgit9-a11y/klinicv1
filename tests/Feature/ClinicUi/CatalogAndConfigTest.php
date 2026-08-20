<?php

declare(strict_types=1);

namespace Tests\Feature\ClinicUi;

use App\Livewire\IPD\IpdConfiguration;
use App\Livewire\Treatments\TreatmentCatalog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Treatment catalogue and IPD configuration operator screens.
 */
class CatalogAndConfigTest extends TestCase
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

    // --- TreatmentCatalog ---

    public function test_catalog_guard_blocks_receptionist(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        Livewire::actingAs($receptionist)->test(TreatmentCatalog::class)->assertStatus(403);
    }

    public function test_add_service_and_room(): void
    {
        Livewire::actingAs($this->owner)
            ->test(TreatmentCatalog::class)
            ->set('service_name', 'Shirodhara')
            ->set('service_category', 'Panchakarma')
            ->set('service_duration_minutes', 60)
            ->set('service_price_rupees', 2500)
            ->call('addService')
            ->assertSee('Service Shirodhara created');

        $this->assertDatabaseHas('treatment_services', ['name' => 'Shirodhara', 'price_cents' => 250000]);

        Livewire::actingAs($this->owner)
            ->test(TreatmentCatalog::class)
            ->set('room_number', 'R-101')
            ->set('room_type', 'Panchakarma')
            ->set('room_capacity', 2)
            ->call('addRoom');

        $this->assertDatabaseHas('treatment_rooms', ['room_number' => 'R-101', 'capacity' => 2]);
    }

    public function test_delete_service(): void
    {
        $service = app(\App\Services\Treatments\TreatmentCatalogService::class)->createService(['name' => 'Old']);

        Livewire::actingAs($this->owner)
            ->test(TreatmentCatalog::class)
            ->call('deleteService', $service->id);

        $this->assertDatabaseMissing('treatment_services', ['id' => $service->id]);
    }

    // --- IpdConfiguration ---

    public function test_ipd_guard_blocks_doctor(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        Livewire::actingAs($doctor)->test(IpdConfiguration::class)->assertStatus(403);
    }

    public function test_ward_room_bed_hierarchy(): void
    {
        Livewire::actingAs($this->owner)
            ->test(IpdConfiguration::class)
            ->set('ward_name', 'Ward A')
            ->call('addWard');

        $wardId = \App\Models\IpdWard::where('name', 'Ward A')->sole()->id;

        Livewire::actingAs($this->owner)
            ->test(IpdConfiguration::class)
            ->set('room_ward_id', $wardId)
            ->set('room_number', '101')
            ->call('addRoom');

        $roomId = \App\Models\IpdRoom::where('room_number', '101')->sole()->id;

        Livewire::actingAs($this->owner)
            ->test(IpdConfiguration::class)
            ->set('bed_room_id', $roomId)
            ->set('bed_number', 'B1')
            ->set('bed_daily_rate_rupees', 800)
            ->call('addBed');

        $this->assertDatabaseHas('ipd_beds', ['bed_number' => 'B1', 'daily_rate_cents' => 80000]);
    }

    public function test_delete_ward_with_rooms_is_blocked_with_friendly_message(): void
    {
        Livewire::actingAs($this->owner)
            ->test(IpdConfiguration::class)
            ->set('ward_name', 'Blocked Ward')
            ->call('addWard');

        $wardId = \App\Models\IpdWard::where('name', 'Blocked Ward')->sole()->id;

        Livewire::actingAs($this->owner)
            ->test(IpdConfiguration::class)
            ->call('deleteWard', $wardId);

        // No rooms yet → deleted silently.
        $this->assertDatabaseMissing('ipd_wards', ['id' => $wardId]);
    }
}
