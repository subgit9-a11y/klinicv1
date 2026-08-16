<?php

declare(strict_types=1);

namespace Tests\Feature\IPD;

use App\Models\IpdAdmission;
use App\Models\IpdBed;
use App\Models\IpdDischargeSummary;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\IpdPolicy;
use App\Services\Auth\Permissions;
use App\Services\IPD\BedNotAvailableException;
use App\Services\IPD\IpdService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpdServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    private function clinicStaff(Tenant $tenant, string $role = 'CLINIC_OWNER'): User
    {
        return User::factory()->forTenant($tenant)->role($role)->create();
    }

    public function test_admit_allocates_bed_and_marks_occupied(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $bed = IpdBed::factory()->create(['status' => 'AVAILABLE']);

        $admission = app(IpdService::class)->admit([
            'patient_id' => $patient->id,
            'ipd_bed_id' => $bed->id,
            'admission_type' => 'ROUTINE',
        ]);

        $this->assertSame('ADMITTED', $admission->status);
        $this->assertNotNull($admission->ipd_number);
        $this->assertSame('OCCUPIED', $bed->fresh()->status);
    }

    public function test_admit_without_bed_creates_admission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        $admission = app(IpdService::class)->admit([
            'patient_id' => $patient->id,
        ]);

        $this->assertSame('ADMITTED', $admission->status);
        $this->assertNull($admission->ipd_bed_id);
    }

    public function test_admit_throws_when_bed_not_available(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $bed = IpdBed::factory()->create(['status' => 'OCCUPIED']);

        $this->expectException(BedNotAvailableException::class);
        app(IpdService::class)->admit([
            'patient_id' => $patient->id,
            'ipd_bed_id' => $bed->id,
        ]);
    }

    public function test_transfer_bed_releases_old_and_occupies_new(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $oldBed = IpdBed::factory()->create(['status' => 'OCCUPIED']);
        $newBed = IpdBed::factory()->create(['status' => 'AVAILABLE']);
        $admission = IpdAdmission::factory()->create([
            'patient_id' => $patient->id,
            'ipd_bed_id' => $oldBed->id,
        ]);

        app(IpdService::class)->transferBed($admission, $newBed->id);

        $this->assertSame('CLEANING', $oldBed->fresh()->status);
        $this->assertSame('OCCUPIED', $newBed->fresh()->status);
        $this->assertSame($newBed->id, $admission->fresh()->ipd_bed_id);
    }

    public function test_transfer_bed_throws_when_target_unavailable(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $oldBed = IpdBed::factory()->create(['status' => 'OCCUPIED']);
        $targetBed = IpdBed::factory()->create(['status' => 'OCCUPIED']);
        $admission = IpdAdmission::factory()->create(['ipd_bed_id' => $oldBed->id]);

        $this->expectException(BedNotAvailableException::class);
        app(IpdService::class)->transferBed($admission, $targetBed->id);
    }

    public function test_discharge_releases_bed_and_sets_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $bed = IpdBed::factory()->create(['status' => 'OCCUPIED', 'daily_rate_cents' => 100000]);
        $admission = IpdAdmission::factory()->create([
            'ipd_bed_id' => $bed->id,
            'admitted_at' => now()->subDays(3),
        ]);

        app(IpdService::class)->discharge($admission, [
            'discharge_diagnosis' => 'Recovered',
            'advice_on_discharge' => 'Rest for 1 week',
        ]);

        $admission->refresh();
        $this->assertSame('DISCHARGED', $admission->status);
        $this->assertNotNull($admission->discharged_at);
        $this->assertSame('CLEANING', $bed->fresh()->status);
    }

    public function test_discharge_creates_discharge_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $bed = IpdBed::factory()->create(['status' => 'OCCUPIED']);
        $admission = IpdAdmission::factory()->create([
            'ipd_bed_id' => $bed->id,
            'provisional_diagnosis' => 'Vata imbalance',
        ]);

        app(IpdService::class)->discharge($admission, [
            'discharge_diagnosis' => 'Recovered',
            'follow_up_days' => 7,
        ]);

        $summary = IpdDischargeSummary::where('ipd_admission_id', $admission->id)->first();
        $this->assertNotNull($summary);
        $this->assertSame('Recovered', $summary->discharge_diagnosis);
        $this->assertSame('Vata imbalance', $summary->admission_diagnosis);
        $this->assertSame(7, $summary->follow_up_days);
    }

    public function test_discharge_generates_invoice_with_bed_charges(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $bed = IpdBed::factory()->create([
            'status' => 'OCCUPIED',
            'daily_rate_cents' => 100000,
        ]);
        $admission = IpdAdmission::factory()->create([
            'ipd_bed_id' => $bed->id,
            'admitted_at' => now()->subDays(3),
        ]);

        app(IpdService::class)->discharge($admission);

        $invoice = Invoice::where('ipd_admission_id', $admission->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('DRAFT', $invoice->status);

        $roomItem = InvoiceItem::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($roomItem);
        $this->assertGreaterThanOrEqual(2, $roomItem->quantity);
        $this->assertSame(100000, $roomItem->unit_price_cents);
        $this->assertSame($roomItem->quantity * 100000, $roomItem->total_cents);
    }

    public function test_discharge_throws_when_already_discharged(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $admission = IpdAdmission::factory()->create(['status' => 'DISCHARGED']);

        $this->expectException(\DomainException::class);
        app(IpdService::class)->discharge($admission);
    }

    public function test_mark_bed_available_from_cleaning(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $bed = IpdBed::factory()->create(['status' => 'CLEANING']);

        $result = app(IpdService::class)->markBedAvailable($bed);

        $this->assertSame('AVAILABLE', $result->status);
    }

    public function test_mark_bed_available_throws_when_not_cleaning(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $bed = IpdBed::factory()->create(['status' => 'OCCUPIED']);

        $this->expectException(\DomainException::class);
        app(IpdService::class)->markBedAvailable($bed);
    }

    public function test_active_admissions_returns_only_admitted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        IpdAdmission::factory()->create(['status' => 'ADMITTED']);
        IpdAdmission::factory()->create(['status' => 'DISCHARGED']);

        $result = app(IpdService::class)->activeAdmissions();

        $this->assertCount(1, $result);
        $this->assertSame('ADMITTED', $result->first()->status);
    }

    public function test_available_beds_returns_only_available(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        IpdBed::factory()->create(['status' => 'AVAILABLE']);
        IpdBed::factory()->create(['status' => 'OCCUPIED']);

        $result = app(IpdService::class)->availableBeds();

        $this->assertCount(1, $result);
        $this->assertSame('AVAILABLE', $result->first()->status);
    }

    // --- Policy ---

    public function test_policy_allows_admit_for_authorized_role(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $user = $this->clinicStaff($tenant);

        $this->assertTrue((new IpdPolicy)->admit($user));
    }

    public function test_policy_blocks_cross_tenant_discharge(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->setTenant($tenantA);
        $admission = IpdAdmission::factory()->create();

        $this->setTenant($tenantB);
        $userB = $this->clinicStaff($tenantB);

        $this->assertFalse((new IpdPolicy)->discharge($userB, $admission));
    }

    public function test_policy_allows_super_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $admission = IpdAdmission::factory()->create();
        $superAdmin = User::factory()->role('SUPER_ADMIN')->create();

        $this->assertTrue((new IpdPolicy)->view($superAdmin, $admission));
        $this->assertTrue((new IpdPolicy)->discharge($superAdmin, $admission));
    }
}
