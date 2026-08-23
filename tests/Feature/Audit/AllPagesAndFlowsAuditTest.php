<?php

namespace Tests\Feature\Audit;

use App\Livewire\Patients\Patient360;
use App\Models\Invoice;
use App\Models\IpdAdmission;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\Therapist;
use App\Models\TreatmentBooking;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AllPagesAndFlowsAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->seed(SystemSettingsSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->tenant = Tenant::where('slug', 'ayur-clinic-demo')->first();
        $this->owner = User::where('email', 'owner@ayurclinic.test')->first();
        $this->superAdmin = User::where('email', 'superadmin@klinic360.test')->first();
    }

    public function test_audit_all_public_pages_render(): void
    {
        foreach (['/', '/login', '/forgot-password'] as $uri) {
            $resp = $this->get($uri);
            $this->assertSame(200, $resp->status(), "Public $uri returned {$resp->status()}");
        }
    }

    public function test_audit_all_authenticated_web_pages_render(): void
    {
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id,
            'k360_uid' => 'K360-P-AUDIT1',
            'first_name' => 'Test', 'last_name' => 'Patient',
            'full_name' => 'Test Patient', 'phone' => '9999999999',
            'is_active' => true,
        ]);

        $pages = [
            '/dashboard', '/patients', '/queue', '/appointments', '/emr',
            '/patients/'.$patient->id, '/patients/'.$patient->id.'/edit', '/profile',
            '/treatments', '/prescriptions', '/payments', '/settings',
            '/ipd', '/billing', '/documents', '/reports', '/notifications', '/ai',
            '/ai/board', '/followups', '/investigations',
            '/settings/clinic', '/notification-templates',
        ];

        foreach ($pages as $uri) {
            $resp = $this->actingAs($this->owner)->get($uri);
            $this->assertSame(200, $resp->status(), "Page $uri returned {$resp->status()}");
            $this->assertStringNotContainsString('exception', $resp->getContent(), "Page $uri contains error");
        }
    }

    public function test_audit_dashboard_shows_live_stats_not_placeholders(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/dashboard');
        $this->assertSame(200, $resp->status());
        $body = $resp->getContent();
        // Placeholder dash must be gone; a formatted number / currency must be present.
        $this->assertStringNotContainsString('>—<', $body, 'Dashboard still renders placeholder "—" stats.');
        $this->assertStringContainsString('text-3xl font-bold', $body, 'Dashboard stat cards missing.');
    }

    public function test_audit_reports_page_renders_live_data(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/reports');
        $this->assertSame(200, $resp->status(), 'Reports page should render for clinic owner.');
        $body = $resp->getContent();
        $this->assertStringContainsString('Operational', $body, 'Reports page missing Operational section.');
        $this->assertStringContainsString('Financial', $body, 'Reports page missing Financial section.');
        $this->assertStringContainsString('Clinical', $body, 'Reports page missing Clinical section.');
        $this->assertStringContainsString('Patients', $body, 'Reports page missing Patients section.');
        // Date filter should be present.
        $this->assertStringContainsString('name="from"', $body);
        $this->assertStringContainsString('name="to"', $body);
    }

    public function test_audit_ai_page_renders_for_clinic_owner(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/ai');
        $this->assertSame(200, $resp->status(), 'AI page should render for clinic owner.');
        $this->assertStringContainsString('AI Assistant', $resp->getContent());
    }

    public function test_audit_notifications_page_renders(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/notifications');
        $this->assertSame(200, $resp->status(), 'Notifications page should render.');
        $this->assertStringContainsString('Notifications', $resp->getContent());
    }

    public function test_audit_documents_page_renders(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/documents');
        $this->assertSame(200, $resp->status(), 'Documents list page should render.');
        $this->assertStringContainsString('No documents found', $resp->getContent());
    }

    public function test_audit_prescription_pdf_download(): void
    {
        app(TenantContext::class)->set($this->tenant->id);
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-PDF1',
            'first_name' => 'Pdf', 'last_name' => 'Test', 'full_name' => 'Pdf Test',
            'phone' => '9111111111', 'is_active' => true,
        ]);
        $rx = Prescription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
        ]);

        $resp = $this->actingAs($this->owner)->get(route('prescriptions.pdf', $rx));
        $this->assertSame(200, $resp->status());
        $this->assertSame('application/pdf', $resp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $resp->getContent());
    }

    public function test_audit_invoice_pdf_download(): void
    {
        app(TenantContext::class)->set($this->tenant->id);
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-PDF2',
            'first_name' => 'Inv', 'last_name' => 'Test', 'full_name' => 'Inv Test',
            'phone' => '9222222222', 'is_active' => true,
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
        ]);

        $resp = $this->actingAs($this->owner)->get(route('invoices.pdf', $invoice));
        $this->assertSame(200, $resp->status());
        $this->assertSame('application/pdf', $resp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $resp->getContent());
    }

    public function test_audit_public_online_booking_page_renders_guest(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->get('/book');
        $this->assertSame(200, $resp->status(), 'Public booking page must be guest-accessible.');
        $this->assertStringContainsString('Book an Online Consultation', $resp->getContent());
    }

    public function test_audit_public_online_booking_creates_appointment(): void
    {
        app(TenantContext::class)->set($this->tenant->id);
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        $resp = $this->post('/book', [
            'first_name' => 'Public',
            'last_name' => 'Booker',
            'phone' => '9333333333',
            'user_id' => $doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
        ]);

        $resp->assertRedirect(route('online-booking.show'));
        $this->assertDatabaseHas('patients', ['phone' => '9333333333', 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $doctor->id,
            'type' => 'ONLINE',
            'status' => 'SCHEDULED',
        ]);
    }

    public function test_audit_ipd_page_renders(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/ipd');
        $this->assertSame(200, $resp->status(), 'IPD page should render.');
        $this->assertStringContainsString('Inpatient Management', $resp->getContent());
        $this->assertStringContainsString('Available beds', $resp->getContent());
    }

    public function test_audit_ipd_admit_discharge_flow(): void
    {
        app(TenantContext::class)->set($this->tenant->id);
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-IPD1',
            'first_name' => 'Ipd', 'last_name' => 'Patient', 'full_name' => 'Ipd Patient',
            'phone' => '9444444444', 'gender' => 'MALE', 'is_active' => true,
        ]);

        // Admit
        $resp = $this->actingAs($this->owner)->post(route('ipd.admit'), [
            'patient_id' => $patient->id,
            'admission_type' => 'ROUTINE',
            'admission_reason' => 'Observation',
        ]);
        $resp->assertRedirect(route('ipd.index'));
        $this->assertDatabaseHas('ipd_admissions', [
            'patient_id' => $patient->id,
            'status' => 'ADMITTED',
        ]);

        $admission = IpdAdmission::where('patient_id', $patient->id)->first();

        // Discharge
        $resp = $this->actingAs($this->owner)->post(route('ipd.discharge', $admission), [
            'diagnosis' => 'Recovered',
            'treatment_given' => 'Rest',
        ]);
        $resp->assertRedirect(route('ipd.index'));
        $this->assertDatabaseHas('ipd_admissions', [
            'id' => $admission->id,
            'status' => 'DISCHARGED',
        ]);
    }

    public function test_audit_billing_page_renders(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $resp = $this->actingAs($this->owner)->get('/billing');
        $this->assertSame(200, $resp->status(), 'Billing page should render.');
        $this->assertStringContainsString('Billing &amp; Cash Register', $resp->getContent());
        $this->assertStringContainsString('Create invoice', $resp->getContent());
    }

    public function test_audit_billing_invoice_issue_pay_flow(): void
    {
        app(TenantContext::class)->set($this->tenant->id);
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-BIL1',
            'first_name' => 'Bill', 'last_name' => 'Patient', 'full_name' => 'Bill Patient',
            'phone' => '9555555555', 'gender' => 'FEMALE', 'is_active' => true,
        ]);

        // Create invoice (DRAFT)
        $resp = $this->actingAs($this->owner)->post(route('billing.invoices.create'), [
            'patient_id' => $patient->id,
            'source' => 'OPD',
        ]);
        $resp->assertRedirect(route('billing.index'));
        $invoice = Invoice::where('patient_id', $patient->id)->latest()->first();
        $this->assertSame('DRAFT', $invoice->status);

        // Add a line item
        $this->actingAs($this->owner)->post(route('billing.items.add', $invoice), [
            'description' => 'Consultation fee',
            'quantity' => 1,
            'unit_price_cents' => 50000,
        ]);

        // Issue the invoice
        $this->actingAs($this->owner)->post(route('billing.issue', $invoice));
        $invoice->refresh();
        $this->assertContains($invoice->status, ['ISSUED', 'PARTIALLY_PAID']);

        // Record a payment
        $this->actingAs($this->owner)->post(route('billing.pay', $invoice), [
            'amount_cents' => 50000,
            'method' => 'CASH',
        ]);
        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'method' => 'CASH']);
    }

    public function test_audit_patient_360_renders_every_tab_without_placeholder(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-TABS',
            'first_name' => 'Tab', 'last_name' => 'Patient', 'full_name' => 'Tab Patient',
            'phone' => '9000000099', 'is_active' => true,
        ]);

        $tabs = [
            'overview', 'timeline', 'appointments', 'opd', 'prescriptions',
            'treatments', 'ipd', 'investigations', 'documents', 'followups',
            'billing', 'payments', 'ai_summary', 'consent', 'abha',
        ];

        foreach ($tabs as $tab) {
            $testable = Livewire::actingAs($this->owner)
                ->test(Patient360::class, ['patient' => $patient])
                ->call('setTab', $tab);
            $body = $testable->html();
            $this->assertStringNotContainsString(
                'This section will be populated as the corresponding modules are built.',
                $body,
                "Tab [{$tab}] still renders the placeholder fallback."
            );
        }
    }

    public function test_audit_super_admin_page_access(): void
    {
        $asOwner = $this->actingAs($this->owner)->get('/super-admin/configuration');
        $this->assertContains($asOwner->status(), [403, 401], "Owner should be denied super-admin (got {$asOwner->status()})");

        $asSa = $this->actingAs($this->superAdmin)->get('/super-admin/configuration');
        $this->assertSame(200, $asSa->status(), "Super admin page returned {$asSa->status()}: ".substr($asSa->getContent(), 0, 500));
    }

    public function test_audit_treatment_api_flow_with_therapist_id(): void
    {
        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-AUDIT2',
            'first_name' => 'T', 'last_name' => 'P', 'full_name' => 'T P',
            'phone' => '9999999888', 'is_active' => true,
        ]);
        $service = TreatmentService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Abhyanga',
            'category' => 'PANCHAKARMA', 'medicine_system' => 'AYURVEDA',
            'duration_minutes' => 60, 'price_cents' => 150000, 'currency' => 'INR',
            'requires_therapist' => true, 'requires_room' => true, 'is_active' => true,
        ]);
        $room = TreatmentRoom::create([
            'tenant_id' => $this->tenant->id, 'room_number' => 'R1',
            'type' => 'TREATMENT', 'is_active' => true,
        ]);
        $therapist = Therapist::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Therapist 1',
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($this->tenant->id);

        $issued = app(TokenService::class)->create($this->owner, 'audit', ['*']);
        $headers = ['Authorization' => 'Bearer '.$issued['token']];

        $resp = $this->withHeaders($headers)->postJson('/api/v1/treatments', [
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => $therapist->id,
            'treatment_room_id' => $room->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00', 'end_time' => '12:00',
        ]);
        $this->assertSame(201, $resp->status(), 'Treatment create failed: '.$resp->content());
        $id = $resp->json('data.id');

        $complete = $this->withHeaders($headers)->postJson("/api/v1/treatments/{$id}/complete");
        $this->assertSame(200, $complete->status(), 'Treatment complete failed: '.$complete->content());

        // Validation should reject a non-existent therapists id now (not a users id).
        $bad = $this->withHeaders($headers)->postJson('/api/v1/treatments', [
            'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'therapist_id' => 999999,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
        ]);
        $this->assertSame(422, $bad->status());
    }

    public function test_audit_treatments_web_page_renders_live_data(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-TRT-WEB',
            'first_name' => 'T', 'last_name' => 'W', 'full_name' => 'T W',
            'phone' => '9888777666', 'is_active' => true,
        ]);
        $service = TreatmentService::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Shirodhara',
            'category' => 'PANCHAKARMA', 'medicine_system' => 'AYURVEDA',
            'duration_minutes' => 45, 'price_cents' => 200000, 'currency' => 'INR',
            'requires_therapist' => true, 'requires_room' => true, 'is_active' => true,
        ]);

        TreatmentBooking::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $patient->id,
            'treatment_service_id' => $service->id,
            'booking_date' => now()->toDateString(),
            'start_time' => '10:00', 'end_time' => '10:45',
            'status' => 'BOOKED', 'payment_mode' => 'POSTPAID',
        ]);

        $resp = $this->actingAs($this->owner)->get('/treatments');
        $this->assertSame(200, $resp->status(), $resp->content());
        $this->assertStringContainsString('Shirodhara', $resp->getContent());
        $this->assertStringContainsString('T W', $resp->getContent());
    }

    public function test_audit_prescriptions_web_page_and_show_render(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-RX-WEB',
            'first_name' => 'R', 'last_name' => 'X', 'full_name' => 'R X',
            'phone' => '9888777665', 'is_active' => true,
        ]);
        $prescription = Prescription::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $patient->id,
            'user_id' => $this->owner->id, 'status' => 'ACTIVE',
            'issued_at' => now(),
        ]);
        $prescription->items()->create([
            'medicine' => 'Ashwagandha', 'form' => 'TABLET', 'dose' => '1 tab',
            'frequency' => 'BD', 'duration' => '30 days',
        ]);

        $list = $this->actingAs($this->owner)->get('/prescriptions');
        $this->assertSame(200, $list->status());
        $this->assertStringContainsString('R X', $list->getContent());

        $show = $this->actingAs($this->owner)->get('/prescriptions/'.$prescription->id);
        $this->assertSame(200, $show->status());
        $this->assertStringContainsString('Ashwagandha', $show->getContent());
    }

    public function test_audit_payments_web_page_renders_totals(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $patient = Patient::create([
            'tenant_id' => $this->tenant->id, 'k360_uid' => 'K360-P-PAY-WEB',
            'first_name' => 'P', 'last_name' => 'Y', 'full_name' => 'P Y',
            'phone' => '9888777664', 'is_active' => true,
        ]);
        Payment::create([
            'tenant_id' => $this->tenant->id, 'patient_id' => $patient->id,
            'payment_number' => 'PAY-AUDIT-1', 'gateway' => 'CASH', 'method' => 'CASH',
            'amount_cents' => 50000, 'currency' => 'INR', 'status' => 'SUCCESS',
            'collected_by' => $this->owner->id, 'paid_at' => now(),
        ]);

        $resp = $this->actingAs($this->owner)->get('/payments');
        $this->assertSame(200, $resp->status());
        $this->assertStringContainsString('₹500.00', $resp->getContent());
        $this->assertStringContainsString('PAY-AUDIT-1', $resp->getContent());
    }

    public function test_audit_settings_web_page_create_and_update_flow(): void
    {
        app(TenantContext::class)->set($this->tenant->id);

        $index = $this->actingAs($this->owner)->get('/settings');
        $this->assertSame(200, $index->status());

        $add = $this->actingAs($this->owner)->post('/settings/add', [
            'key' => 'audit_clinic_name', 'value' => 'Audit Clinic', 'category' => 'general',
        ]);
        $add->assertRedirect('/settings');
        $this->assertDatabaseHas('tenant_settings', ['key' => 'audit_clinic_name', 'value' => 'Audit Clinic']);

        $list = $this->actingAs($this->owner)->get('/settings');
        $this->assertStringContainsString('audit_clinic_name', $list->getContent());

        $existing = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('key', 'audit_clinic_name')->first();
        $update = $this->actingAs($this->owner)->post('/settings', [
            'settings' => [[
                'key' => 'audit_clinic_name', 'category' => 'general', 'value' => 'Renamed Clinic',
            ]],
        ]);
        $update->assertRedirect('/settings');
        $this->assertSame('Renamed Clinic', $existing->fresh()->value);
    }
}
