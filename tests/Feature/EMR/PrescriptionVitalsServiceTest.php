<?php

declare(strict_types=1);

namespace Tests\Feature\EMR;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vital;
use App\Policies\PrescriptionPolicy;
use App\Services\EMR\PrescriptionService;
use App\Services\EMR\VitalsService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionVitalsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function doctor(Tenant $tenant): User
    {
        return User::factory()->forTenant($tenant)->role('DOCTOR')->create();
    }

    // --- PrescriptionService ---

    public function test_create_prescription_with_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $doctor = $this->doctor($tenant);

        $service = app(PrescriptionService::class);

        $prescription = $service->create($patient->id, [
            'notes' => 'Take with warm water',
        ], [
            ['medicine' => 'Ashwagandha', 'form' => 'churna', 'dose' => '1 tsp', 'frequency' => 'BD'],
            ['medicine' => 'Triphala', 'form' => 'tablet', 'dose' => '2 tab', 'frequency' => 'HS'],
        ], $doctor->id);

        $this->assertSame('ACTIVE', $prescription->status);
        $this->assertSame($patient->id, $prescription->patient_id);
        $this->assertSame($doctor->id, $prescription->user_id);
        $this->assertCount(2, $prescription->items);
        $this->assertSame('Ashwagandha', $prescription->items->first()->medicine);
        $this->assertNotNull($prescription->issued_at);
    }

    public function test_amend_creates_new_version_and_marks_original_amended(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $doctor = $this->doctor($tenant);

        $service = app(PrescriptionService::class);

        $original = $service->create($patient->id, [], [
            ['medicine' => 'Old Medicine', 'dose' => '1 tab'],
        ], $doctor->id);

        $amended = $service->amend($original, ['notes' => 'Updated dose'], [
            ['medicine' => 'New Medicine', 'dose' => '2 tab'],
        ]);

        $this->assertSame('AMENDED', $original->fresh()->status);
        $this->assertSame('ACTIVE', $amended->status);
        $this->assertSame($patient->id, $amended->patient_id);
        $this->assertSame('New Medicine', $amended->items->first()->medicine);
        $this->assertNotSame($original->id, $amended->id);
    }

    public function test_amend_throws_for_non_active_prescription(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(PrescriptionService::class);

        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'COMPLETED',
        ]);

        $this->expectException(\DomainException::class);
        $service->amend($prescription, [], [['medicine' => 'Test']]);
    }

    public function test_complete_sets_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(PrescriptionService::class);

        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ACTIVE',
        ]);

        $service->complete($prescription);

        $this->assertSame('COMPLETED', $prescription->fresh()->status);
    }

    public function test_cancel_sets_status_and_appends_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(PrescriptionService::class);

        $prescription = Prescription::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'ACTIVE',
            'notes' => 'Original notes',
        ]);

        $service->cancel($prescription, 'Patient allergic');

        $prescription->refresh();
        $this->assertSame('CANCELLED', $prescription->status);
        $this->assertStringContainsString('Patient allergic', $prescription->notes);
    }

    public function test_for_patient_returns_ordered_with_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(PrescriptionService::class);

        $old = $service->create($patient->id, [], [['medicine' => 'Old']]);
        sleep(0); // ensure ordering by id
        $new = $service->create($patient->id, [], [['medicine' => 'New']]);

        $result = $service->forPatient($patient->id);

        $this->assertCount(2, $result);
        $this->assertSame($new->id, $result->first()->id);
        $this->assertTrue($result->first()->relationLoaded('items'));
    }

    // --- VitalsService ---

    public function test_record_vitals_basic(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $doctor = $this->doctor($tenant);

        $service = app(VitalsService::class);

        $vital = $service->record($patient->id, [
            'systolic_bp' => '120',
            'diastolic_bp' => '80',
            'pulse' => '72',
            'temperature' => '98.6',
        ], null, $doctor->id);

        $this->assertSame($patient->id, $vital->patient_id);
        $this->assertSame('120', $vital->systolic_bp);
        $this->assertSame($doctor->id, $vital->recorded_by);
        $this->assertNotNull($vital->recorded_at);
    }

    public function test_record_vitals_auto_calculates_bmi(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(VitalsService::class);

        $vital = $service->record($patient->id, [
            'height' => '170', // cm
            'weight' => '70',  // kg
        ]);

        // BMI = 70 / (1.7^2) = 24.22
        $this->assertSame('24.2', $vital->bmi);
    }

    public function test_record_vitals_does_not_override_explicit_bmi(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(VitalsService::class);

        $vital = $service->record($patient->id, [
            'height' => '170',
            'weight' => '70',
            'bmi' => '30.0',
        ]);

        $this->assertSame('30.0', $vital->bmi);
    }

    public function test_record_vitals_with_custom_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(VitalsService::class);

        $vital = $service->record($patient->id, [
            'custom_vitals' => ['prakriti' => 'Vata-Pitta', 'agni' => 'Tikshna'],
        ]);

        $this->assertIsArray($vital->custom_vitals);
        $this->assertSame('Vata-Pitta', $vital->custom_vitals['prakriti']);
    }

    public function test_for_patient_returns_ordered_vitals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(VitalsService::class);

        $service->record($patient->id, ['pulse' => '70']);
        $service->record($patient->id, ['pulse' => '75']);

        $result = $service->forPatient($patient->id);

        $this->assertCount(2, $result);
        $this->assertSame('75', $result->first()->pulse);
    }

    public function test_latest_for_patient_returns_most_recent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(VitalsService::class);

        Vital::factory()->create([
            'patient_id' => $patient->id,
            'pulse' => '70',
            'recorded_at' => now()->subHour(),
        ]);
        $latest = Vital::factory()->create([
            'patient_id' => $patient->id,
            'pulse' => '80',
            'recorded_at' => now(),
        ]);

        $result = $service->latestForPatient($patient->id);

        $this->assertNotNull($result);
        $this->assertSame($latest->id, $result->id);
        $this->assertSame('80', $result->pulse);
    }

    public function test_latest_for_patient_returns_null_when_none(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $service = app(VitalsService::class);

        $this->assertNull($service->latestForPatient($patient->id));
    }

    // --- PrescriptionPolicy ---

    public function test_policy_allows_doctor_to_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $doctor = $this->doctor($tenant);

        $this->assertTrue((new PrescriptionPolicy)->create($doctor));
    }

    public function test_policy_blocks_cross_tenant_view(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $patient = Patient::factory()->create();
        $prescription = Prescription::factory()->create(['patient_id' => $patient->id]);

        $this->setTenant($tenantB);
        $doctorB = $this->doctor($tenantB);

        $this->assertFalse((new PrescriptionPolicy)->view($doctorB, $prescription));
    }

    public function test_policy_allows_same_tenant_doctor_view(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $prescription = Prescription::factory()->create(['patient_id' => $patient->id]);
        $doctor = $this->doctor($tenant);

        $this->assertTrue((new PrescriptionPolicy)->view($doctor, $prescription));
    }

    public function test_policy_allows_super_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $prescription = Prescription::factory()->create(['patient_id' => $patient->id]);

        $superAdmin = User::factory()->role('SUPER_ADMIN')->create();

        $this->assertTrue((new PrescriptionPolicy)->view($superAdmin, $prescription));
        $this->assertTrue((new PrescriptionPolicy)->amend($superAdmin, $prescription));
    }

    public function test_policy_blocks_receptionist_from_creating(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $receptionist = User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create();

        $this->assertFalse((new PrescriptionPolicy)->create($receptionist));
    }
}
