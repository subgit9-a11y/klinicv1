<?php

declare(strict_types=1);

namespace Tests\Feature\Treatments;

use App\Models\Tenant;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Treatments\TreatmentCatalogService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentCatalogServiceTest extends TestCase
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

    public function test_create_service_stamps_tenant_and_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(TreatmentCatalogService::class)->createService([
            'name' => 'Abhyanga',
            'category' => 'Panchakarma',
            'medicine_system' => 'AYURVEDA',
            'duration_minutes' => 60,
            'price_cents' => 150000,
        ]);

        $this->assertSame($tenant->id, $service->tenant_id);
        $this->assertSame('INR', $service->currency);
        $this->assertTrue($service->is_active);
        $this->assertSame(150000, $service->price_cents);
    }

    public function test_update_service_changes_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $service = TreatmentService::factory()->create(['tenant_id' => $tenant->id]);

        $updated = app(TreatmentCatalogService::class)->updateService($service, [
            'price_cents' => 90000,
            'is_active' => false,
        ]);

        $this->assertSame(90000, $updated->price_cents);
        $this->assertFalse($updated->is_active);
    }

    public function test_create_room_with_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $room = app(TreatmentCatalogService::class)->createRoom([
            'room_number' => 'TR-01',
            'capacity' => 2,
        ]);

        $this->assertSame($tenant->id, $room->tenant_id);
        $this->assertSame('AVAILABLE', $room->status);
        $this->assertSame(2, $room->capacity);
    }

    public function test_api_clinic_owner_can_create_service(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/treatment-services', [
                'name' => 'Shirodhara',
                'medicine_system' => 'AYURVEDA',
                'duration_minutes' => 45,
                'price_cents' => 120000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('name', 'Shirodhara')
            ->assertJsonPath('price', 1200);
    }

    public function test_api_doctor_can_view_but_not_create_services(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        TreatmentService::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Visible']);

        $this->withHeaders($this->tokenHeader($doctor))
            ->getJson('/api/v1/treatment-services')
            ->assertSuccessful();

        $this->withHeaders($this->tokenHeader($doctor))
            ->postJson('/api/v1/treatment-services', ['name' => 'X'])
            ->assertStatus(403);
    }

    public function test_api_clinic_owner_can_manage_rooms(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $room = TreatmentRoom::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->tokenHeader($owner))
            ->getJson('/api/v1/treatment-rooms')
            ->assertSuccessful()
            ->assertJsonCount(1);

        $this->withHeaders($this->tokenHeader($owner))
            ->deleteJson("/api/v1/treatment-rooms/{$room->id}")
            ->assertStatus(204);
    }

    public function test_cross_tenant_service_access_denied(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $ownerA = User::factory()->forTenant($tenantA)->create(['role' => 'CLINIC_OWNER']);

        // Create tenantB's service outside the current (tenantA) context so the
        // BelongsToTenant creating hook does not stamp tenantA's id onto it.
        $ctx = app(TenantContext::class);
        $ctx->set($tenantB->id);
        $serviceB = TreatmentService::factory()->create();
        $ctx->set($tenantA->id);

        $this->withHeaders($this->tokenHeader($ownerA))
            ->getJson("/api/v1/treatment-services/{$serviceB->id}")
            ->assertNotFound();
    }
}
