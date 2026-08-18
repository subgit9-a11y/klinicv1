<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\IpdAdmission;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Tenant;
use App\Models\TreatmentBooking;
use App\Services\Reports\ReportService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_appointment_summary_counts_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        Appointment::factory()->for($patient)->create(['status' => 'SCHEDULED', 'appointment_date' => today()]);
        Appointment::factory()->for($patient)->create(['status' => 'COMPLETED', 'appointment_date' => today()]);
        Appointment::factory()->for($patient)->create(['status' => 'CANCELLED', 'appointment_date' => today()]);

        $from = today()->subDay();
        $to = today()->addDay();

        $result = app(ReportService::class)->appointmentSummary($from, $to);

        $this->assertSame(1, $result['scheduled']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(1, $result['cancelled']);
        $this->assertSame(3, $result['total']);
    }

    public function test_appointment_summary_excludes_out_of_range(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        Appointment::factory()->for($patient)->create(['status' => 'SCHEDULED', 'appointment_date' => today()]);
        Appointment::factory()->for($patient)->create(['status' => 'SCHEDULED', 'appointment_date' => today()->subDays(10)]);

        $result = app(ReportService::class)->appointmentSummary(today()->subDay(), today()->addDay());

        $this->assertSame(1, $result['scheduled']);
        $this->assertSame(1, $result['total']);
    }

    public function test_revenue_summary_calculates_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        Invoice::factory()->for($patient)->create([
            'status' => 'PAID',
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
            'amount_due_cents' => 0,
        ]);
        Invoice::factory()->for($patient)->create([
            'status' => 'DUE',
            'total_cents' => 30000,
            'amount_paid_cents' => 0,
            'amount_due_cents' => 30000,
        ]);

        $result = app(ReportService::class)->revenueSummary(now()->subDay(), now()->addDay());

        // 500 + 0 = 500 revenue (only PAID), 500 collected, 300 outstanding
        $this->assertSame(500.0, $result['total_revenue']);
        $this->assertSame(500.0, $result['total_collected']);
        $this->assertSame(300.0, $result['total_outstanding']);
        $this->assertSame(2, $result['invoice_count']);
    }

    public function test_collections_by_method(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $invoice = Invoice::factory()->create();
        Payment::factory()->for($invoice)->create(['method' => 'CASH', 'amount_cents' => 10000, 'status' => 'COMPLETED']);
        Payment::factory()->for($invoice)->create(['method' => 'UPI', 'amount_cents' => 20000, 'status' => 'COMPLETED']);

        $result = app(ReportService::class)->collectionsByMethod(now()->subDay(), now()->addDay());

        $this->assertSame(100.0, $result['CASH'] ?? 0);
        $this->assertSame(200.0, $result['UPI'] ?? 0);
    }

    public function test_consultations_by_system(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create();

        Consultation::factory()->for($patient)->for($appointment)->create(['medicine_system' => 'AYURVEDA']);
        Consultation::factory()->for($patient)->for($appointment)->create(['medicine_system' => 'SIDDHA']);

        $result = app(ReportService::class)->consultationsBySystem(now()->subDay(), now()->addDay());

        $this->assertSame(1, $result['AYURVEDA'] ?? 0);
        $this->assertSame(1, $result['SIDDHA'] ?? 0);
    }

    public function test_prescription_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create();
        $consultation = Consultation::factory()->for($patient)->for($appointment)->create();

        Prescription::factory()->for($consultation)->create(['status' => 'DRAFT']);
        Prescription::factory()->for($consultation)->create(['status' => 'COMPLETED']);

        $result = app(ReportService::class)->prescriptionSummary(now()->subDay(), now()->addDay());

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['completed']);
    }

    public function test_ipd_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        IpdAdmission::factory()->for($patient)->create([
            'status' => 'ADMITTED',
            'admitted_at' => now(),
        ]);
        IpdAdmission::factory()->for($patient)->create([
            'status' => 'DISCHARGED',
            'admitted_at' => now()->subDays(3),
            'discharged_at' => now(),
        ]);

        $result = app(ReportService::class)->ipdSummary(now()->subDay(), now()->addDay());

        $this->assertSame(1, $result['current_admissions']);
        $this->assertSame(1, $result['discharged_in_period']);
        $this->assertGreaterThan(0, $result['avg_los_days']);
    }

    public function test_patient_acquisition(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        // New patient (created today) with appointment today.
        $newPatient = Patient::factory()->create(['created_at' => now()]);
        Appointment::factory()->for($newPatient)->create(['appointment_date' => today()]);

        // Returning patient (created before range) with appointment today.
        $returningPatient = Patient::factory()->create(['created_at' => now()->subDays(10)]);
        Appointment::factory()->for($returningPatient)->create(['appointment_date' => today()]);

        $result = app(ReportService::class)->patientAcquisition(now()->subDay(), now()->addDay());

        $this->assertSame(1, $result['new_patients']);
        $this->assertSame(1, $result['returning_patients']);
        $this->assertSame(2, $result['total_visits']);
    }

    public function test_patient_demographics(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        Patient::factory()->create(['gender' => 'MALE']);
        Patient::factory()->create(['gender' => 'FEMALE']);
        Patient::factory()->create(['gender' => 'FEMALE']);

        $result = app(ReportService::class)->patientDemographics();

        $this->assertSame(1, $result['MALE'] ?? 0);
        $this->assertSame(2, $result['FEMALE'] ?? 0);
    }

    public function test_treatment_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();

        TreatmentBooking::factory()->for($patient)->create(['status' => 'BOOKED']);
        TreatmentBooking::factory()->for($patient)->create(['status' => 'COMPLETED']);

        $result = app(ReportService::class)->treatmentSummary(now()->subDay(), now()->addDay());

        $this->assertSame(1, $result['booked']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(2, $result['total']);
    }

    public function test_dashboard_combines_all_categories(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);
        $patient = Patient::factory()->create();
        Appointment::factory()->for($patient)->create(['status' => 'SCHEDULED', 'appointment_date' => today()]);

        $dashboard = app(ReportService::class)->dashboard(now()->subDay(), now()->addDay());

        $this->assertArrayHasKey('period', $dashboard);
        $this->assertArrayHasKey('operational', $dashboard);
        $this->assertArrayHasKey('financial', $dashboard);
        $this->assertArrayHasKey('clinical', $dashboard);
        $this->assertArrayHasKey('patients', $dashboard);
        $this->assertSame(1, $dashboard['operational']['appointments']['scheduled']);
    }

    public function test_reports_are_tenant_scoped(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $this->setTenant($tenant1);
        $patient1 = Patient::factory()->create();
        Appointment::factory()->for($patient1)->create(['status' => 'SCHEDULED', 'appointment_date' => today()]);

        $this->setTenant($tenant2);
        $patient2 = Patient::factory()->create();
        Appointment::factory()->for($patient2)->create(['status' => 'SCHEDULED', 'appointment_date' => today()]);

        // Tenant 1 should only see 1 appointment.
        $this->setTenant($tenant1);
        $result1 = app(ReportService::class)->appointmentSummary(now()->subDay(), now()->addDay());
        $this->assertSame(1, $result1['scheduled']);

        // Tenant 2 should only see 1 appointment.
        $this->setTenant($tenant2);
        $result2 = app(ReportService::class)->appointmentSummary(now()->subDay(), now()->addDay());
        $this->assertSame(1, $result2['scheduled']);
    }
}
