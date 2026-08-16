<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTeleconsultationsTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function tokenHeader(User $user): array
    {
        $issued = app(TokenService::class)->create($user, 'test', ['*']);

        return ['Authorization' => 'Bearer ' . $issued['token']];
    }

    public function test_can_list_teleconsultations(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Teleconsultation::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/teleconsultations');

        $response->assertSuccessful()->assertJsonCount(2, 'data');
    }

    public function test_can_show_teleconsultation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $tele = Teleconsultation::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson("/api/v1/teleconsultations/{$tele->id}");

        $response->assertSuccessful()
            ->assertJsonPath('id', $tele->id)
            ->assertJsonPath('patient.id', $patient->id);
    }

    public function test_teleconsultations_scoped_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $userA = User::factory()->forTenant($tenantA)->create(['role' => 'DOCTOR']);
        $patientA = Patient::factory()->create(['tenant_id' => $tenantA->id]);
        $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

        Teleconsultation::factory()->create([
            'tenant_id' => $tenantA->id,
            'patient_id' => $patientA->id,
        ]);

        $this->setTenant($tenantB);
        Teleconsultation::factory()->create([
            'tenant_id' => $tenantB->id,
            'patient_id' => $patientB->id,
        ]);

        $this->setTenant($tenantA);
        $response = $this->withHeaders($this->tokenHeader($userA))
            ->getJson('/api/v1/teleconsultations');

        $response->assertSuccessful()->assertJsonCount(1, 'data');
    }
}
