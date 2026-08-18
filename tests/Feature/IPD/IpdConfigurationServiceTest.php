<?php

declare(strict_types=1);

namespace Tests\Feature\IPD;

use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\IPD\IpdConfigurationService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpdConfigurationServiceTest extends TestCase
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

    public function test_create_ward_with_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $ward = app(IpdConfigurationService::class)->createWard([
            'name' => 'General Ward A',
            'type' => 'GENERAL',
        ]);

        $this->assertSame($tenant->id, $ward->tenant_id);
        $this->assertTrue($ward->is_active);
    }

    public function test_cannot_delete_ward_with_rooms(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $ward = IpdWard::factory()->create(['tenant_id' => $tenant->id]);
        IpdRoom::factory()->create(['tenant_id' => $tenant->id, 'ipd_ward_id' => $ward->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(IpdConfigurationService::class)->deleteWard($ward);
    }

    public function test_cannot_delete_room_with_beds(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $ward = IpdWard::factory()->create(['tenant_id' => $tenant->id]);
        $room = IpdRoom::factory()->create(['tenant_id' => $tenant->id, 'ipd_ward_id' => $ward->id]);
        IpdBed::factory()->create(['tenant_id' => $tenant->id, 'ipd_room_id' => $room->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(IpdConfigurationService::class)->deleteRoom($room);
    }

    public function test_cannot_delete_occupied_bed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $ward = IpdWard::factory()->create(['tenant_id' => $tenant->id]);
        $room = IpdRoom::factory()->create(['tenant_id' => $tenant->id, 'ipd_ward_id' => $ward->id]);
        $bed = IpdBed::factory()->create([
            'tenant_id' => $tenant->id,
            'ipd_room_id' => $room->id,
            'status' => 'OCCUPIED',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(IpdConfigurationService::class)->deleteBed($bed);
    }

    public function test_update_bed_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $ward = IpdWard::factory()->create(['tenant_id' => $tenant->id]);
        $room = IpdRoom::factory()->create(['tenant_id' => $tenant->id, 'ipd_ward_id' => $ward->id]);
        $bed = IpdBed::factory()->create(['tenant_id' => $tenant->id, 'ipd_room_id' => $room->id]);

        $updated = app(IpdConfigurationService::class)->updateBedStatus($bed, 'MAINTENANCE');

        $this->assertSame('MAINTENANCE', $updated->status);
    }

    public function test_api_clinic_owner_can_create_ward_room_bed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $ward = $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/ipd-wards', ['name' => 'ICU Block', 'type' => 'ICU'])
            ->assertStatus(201)
            ->assertJsonPath('name', 'ICU Block')
            ->assertJsonPath('type', 'ICU')
            ->json('id');

        $room = $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/ipd-rooms', [
                'ipd_ward_id' => $ward,
                'room_number' => 'ICU-101',
                'type' => 'ICU',
            ])
            ->assertStatus(201)
            ->assertJsonPath('room_number', 'ICU-101')
            ->json('id');

        $this->withHeaders($this->tokenHeader($owner))
            ->postJson('/api/v1/ipd-beds', [
                'ipd_room_id' => $room,
                'bed_number' => 'ICU-101-A',
                'daily_rate_cents' => 500000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('bed_number', 'ICU-101-A')
            ->assertJsonPath('daily_rate', 5000);
    }

    public function test_api_ipd_staff_cannot_create_wards(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $staff = User::factory()->forTenant($tenant)->create(['role' => 'IPD_STAFF']);

        $this->withHeaders($this->tokenHeader($staff))
            ->postJson('/api/v1/ipd-wards', ['name' => 'X'])
            ->assertStatus(403);
    }

    public function test_api_clinic_owner_can_list_and_update_beds(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $owner = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $ward = IpdWard::factory()->create(['tenant_id' => $tenant->id]);
        $room = IpdRoom::factory()->create(['tenant_id' => $tenant->id, 'ipd_ward_id' => $ward->id]);
        $bed = IpdBed::factory()->create(['tenant_id' => $tenant->id, 'ipd_room_id' => $room->id]);

        $this->withHeaders($this->tokenHeader($owner))
            ->getJson('/api/v1/ipd-beds')
            ->assertSuccessful()
            ->assertJsonCount(1);

        $this->withHeaders($this->tokenHeader($owner))
            ->patchJson("/api/v1/ipd-beds/{$bed->id}/status", ['status' => 'MAINTENANCE'])
            ->assertSuccessful()
            ->assertJsonPath('status', 'MAINTENANCE');
    }
}
