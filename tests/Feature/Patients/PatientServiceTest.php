<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Tenant;
use App\Services\Patients\PatientService;
use App\Services\Patients\PatientUidService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_uid_service_generates_valid_format(): void
    {
        $uid = app(PatientUidService::class)->generate();

        $this->assertMatchesRegularExpression('/^K360-P-\d{10}$/', $uid);
        $this->assertTrue(app(PatientUidService::class)->isValid($uid));
    }

    public function test_uid_is_unique_across_many_generations(): void
    {
        $service = app(PatientUidService::class);
        $uids = [];

        for ($i = 0; $i < 200; $i++) {
            $uids[] = $service->generate();
        }

        $this->assertSame(200, count(array_unique($uids)));
    }

    public function test_register_generates_uid_and_scopes_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $patient = app(PatientService::class)->register([
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'phone' => '9876543210',
            'gender' => 'MALE',
            'dob' => '1990-05-15',
        ]);

        $this->assertSame($tenant->id, $patient->tenant_id);
        $this->assertNotEmpty($patient->k360_uid);
        $this->assertMatchesRegularExpression('/^K360-P-\d{10}$/', $patient->k360_uid);
        $this->assertSame('Aarav', $patient->first_name);
    }

    public function test_register_with_explicit_uid_is_respected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $patient = app(PatientService::class)->register([
            'k360_uid' => 'K360-P-0000000001',
            'first_name' => 'Diya',
            'phone' => '9000000001',
            'gender' => 'FEMALE',
        ]);

        $this->assertSame('K360-P-0000000001', $patient->k360_uid);
    }

    public function test_duplicate_phone_within_tenant_is_blocked(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(PatientService::class);
        $service->register(['first_name' => 'A', 'phone' => '9111111111', 'gender' => 'MALE']);

        $this->expectException(QueryException::class);
        $service->register(['first_name' => 'B', 'phone' => '9111111111', 'gender' => 'FEMALE']);
    }

    public function test_same_phone_across_different_tenants_is_allowed(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->setTenant($tenantA);
        app(PatientService::class)->register(['first_name' => 'A', 'phone' => '9222222222', 'gender' => 'MALE']);

        $this->setTenant($tenantB);
        $patientB = app(PatientService::class)->register(['first_name' => 'B', 'phone' => '9222222222', 'gender' => 'FEMALE']);

        $this->assertSame($tenantB->id, $patientB->tenant_id);
    }

    public function test_find_duplicate_by_phone_returns_match(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(PatientService::class);
        $created = $service->register(['first_name' => 'A', 'phone' => '9333333333', 'gender' => 'MALE']);

        $dup = $service->findDuplicateByPhone('9333333333');

        $this->assertNotNull($dup);
        $this->assertSame($created->id, $dup->id);
    }

    public function test_find_duplicate_by_phone_returns_null_when_absent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $this->assertNull(app(PatientService::class)->findDuplicateByPhone('9999999999'));
    }

    public function test_search_finds_by_uid_phone_and_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(PatientService::class);
        $patient = $service->register(['first_name' => 'Vikram', 'last_name' => 'Iyer', 'phone' => '9444444444', 'gender' => 'MALE']);

        $this->assertCount(1, $service->search($patient->k360_uid));
        $this->assertCount(1, $service->search('9444444444'));
        $this->assertCount(1, $service->search('Vikram'));
        $this->assertCount(0, $service->search('Nonexistent'));
    }

    public function test_search_is_tenant_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->setTenant($tenantA);
        app(PatientService::class)->register(['first_name' => 'Asha', 'phone' => '9555555555', 'gender' => 'FEMALE']);

        $this->setTenant($tenantB);
        $this->assertCount(0, app(PatientService::class)->search('Asha'));
    }

    public function test_find_by_uid_scoped_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $patient = app(PatientService::class)->register(['first_name' => 'Ravi', 'phone' => '9666666666', 'gender' => 'MALE']);

        $found = app(PatientService::class)->findByUid($patient->k360_uid);
        $this->assertNotNull($found);
        $this->assertSame($patient->id, $found->id);
    }

    public function test_update_never_changes_uid_or_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(PatientService::class);
        $patient = $service->register(['first_name' => 'Old', 'phone' => '9777777777', 'gender' => 'MALE']);

        $originalUid = $patient->k360_uid;

        $service->update($patient, [
            'first_name' => 'New',
            'k360_uid' => 'K360-P-9999999999', // should be ignored
            'tenant_id' => 999999,              // should be ignored
        ]);

        $this->assertSame('New', $patient->fresh()->first_name);
        $this->assertSame($originalUid, $patient->fresh()->k360_uid);
        $this->assertSame($tenant->id, $patient->fresh()->tenant_id);
    }

    public function test_register_creates_related_identifiers_and_consents(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $patient = app(PatientService::class)->register(
            ['first_name' => 'Meena', 'phone' => '9888888888', 'gender' => 'FEMALE'],
            [
                'identifiers' => [
                    ['type' => 'ABHA', 'value' => 'abha-123', 'is_primary' => true],
                ],
                'consents' => [
                    ['consent_type' => 'TREATMENT', 'granted' => true, 'description' => 'General treatment consent'],
                ],
            ]
        );

        $this->assertCount(1, $patient->identifiers);
        $this->assertSame('ABHA', $patient->identifiers->first()->type);
        $this->assertCount(1, $patient->consents);
        $this->assertTrue($patient->consents->first()->granted);
        $this->assertNotNull($patient->consents->first()->consented_at);
    }

    public function test_patient_operations_require_tenant_context(): void
    {
        app(TenantContext::class)->forget();

        $this->expectException(\RuntimeException::class);
        app(PatientService::class)->register(['first_name' => 'X', 'phone' => '9999999990', 'gender' => 'MALE']);
    }
}
