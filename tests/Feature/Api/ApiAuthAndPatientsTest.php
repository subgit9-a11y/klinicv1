<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthAndPatientsTest extends TestCase
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

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'doctor@clinic.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'doctor@clinic.test',
            'password' => 'secret123',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'doctor@clinic.test',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'doctor@clinic.test',
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_inactive_account(): void
    {
        User::factory()->create([
            'email' => 'inactive@clinic.test',
            'password' => bcrypt('secret123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@clinic.test',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    public function test_protected_endpoint_requires_token(): void
    {
        $this->getJson('/api/v1/patients')->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer k360_bogus')
            ->getJson('/api/v1/patients')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_patients(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);
        Patient::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/patients');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data');
    }

    public function test_authenticated_user_can_create_patient(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/patients', [
                'first_name' => 'Aarav',
                'last_name' => 'Sharma',
                'phone' => '9876543210',
                'gender' => 'MALE',
                'dob' => '1990-05-15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Aarav')
            ->assertJsonPath('data.k360_uid', fn ($v) => str_starts_with($v, 'K360-P-'));

        $this->assertDatabaseHas('patients', ['phone' => '9876543210']);
    }

    public function test_patient_store_validates_required_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'RECEPTIONIST']);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/v1/patients', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'phone']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $issued = app(TokenService::class)->create($user, 'test');

        $this->withHeader('Authorization', 'Bearer ' . $issued['token'])
            ->postJson('/api/v1/auth/logout')
            ->assertSuccessful();

        // Same token no longer works.
        $this->withHeader('Authorization', 'Bearer ' . $issued['token'])
            ->getJson('/api/v1/patients')
            ->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $issued = app(TokenService::class)->create($user, 'test', ['*'], now()->subMinute());

        $this->withHeader('Authorization', 'Bearer ' . $issued['token'])
            ->getJson('/api/v1/patients')
            ->assertUnauthorized();
    }

    public function test_token_is_scoped_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $user = User::factory()->forTenant($tenantA)->create(['role' => 'RECEPTIONIST']);
        Patient::factory()->create(['tenant_id' => $tenantA->id]);

        // Bypass the global scope to seed a patient in another tenant.
        $patient = Patient::factory()->make(['tenant_id' => $tenantB->id]);
        Patient::withoutGlobalScope('tenant')->insert($patient->getAttributes());

        $response = $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/v1/patients');

        $response->assertSuccessful()->assertJsonCount(1, 'data');
    }
}
