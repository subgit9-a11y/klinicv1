<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPrescriptionsTest extends TestCase
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

    public function test_can_create_prescription_for_patient(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/patients/{$patient->id}/prescriptions", [
                'notes' => 'After meals',
                'items' => [
                    [
                        'medicine' => 'Ashwagandha 500mg',
                        'form' => 'TABLET',
                        'dose' => '1 tablet',
                        'frequency' => 'BID',
                        'duration' => '30 days',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_prescription_store_validates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/patients/{$patient->id}/prescriptions", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_can_list_patient_prescriptions(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        Prescription::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson("/api/v1/patients/{$patient->id}/prescriptions");

        $response->assertSuccessful()->assertJsonCount(3, 'data');
    }

    public function test_can_show_prescription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $prescription = Prescription::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson("/api/v1/prescriptions/{$prescription->id}");

        $response->assertSuccessful()
            ->assertJsonPath('id', $prescription->id);
    }

    public function test_receptionist_cannot_create_prescription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson("/api/v1/patients/{$patient->id}/prescriptions", [
                'items' => [['medicine' => 'Test']],
            ]);

        $response->assertForbidden();
    }
}
