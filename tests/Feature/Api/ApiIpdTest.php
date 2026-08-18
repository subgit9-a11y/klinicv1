<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\IpdAdmission;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiIpdTest extends TestCase
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

    public function test_can_list_ipd_admissions(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        IpdAdmission::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/ipd-admissions');

        $response->assertSuccessful()->assertJsonCount(2, 'data');
    }

    public function test_can_admit_patient_without_bed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/ipd-admissions', [
                'patient_id' => $patient->id,
                'admission_type' => 'ROUTINE',
                'admission_reason' => 'Observation',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ADMITTED')
            ->assertJsonPath('data.patient.id', $patient->id);
    }

    public function test_ipd_admission_validates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/ipd-admissions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id']);
    }

    public function test_can_discharge_admitted_patient(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'CLINIC_OWNER']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $admission = IpdAdmission::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'status' => 'ADMITTED',
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/ipd-admissions/{$admission->id}/discharge", [
                'discharge_diagnosis' => 'Recovered',
                'advice_on_discharge' => 'Rest for 3 days',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'DISCHARGED');
    }

    public function test_ipd_admission_scoped_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $userA = User::factory()->forTenant($tenantA)->create(['role' => 'CLINIC_OWNER']);
        $patientA = Patient::factory()->create(['tenant_id' => $tenantA->id]);
        $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

        IpdAdmission::factory()->create([
            'tenant_id' => $tenantA->id,
            'patient_id' => $patientA->id,
        ]);

        // Tenant B's admission should not be visible to tenant A's user.
        $this->setTenant($tenantB);
        IpdAdmission::factory()->create([
            'tenant_id' => $tenantB->id,
            'patient_id' => $patientB->id,
        ]);

        $this->setTenant($tenantA);
        $response = $this->withHeaders($this->tokenHeader($userA))
            ->getJson('/api/v1/ipd-admissions');

        $response->assertSuccessful()->assertJsonCount(1, 'data');
    }
}
