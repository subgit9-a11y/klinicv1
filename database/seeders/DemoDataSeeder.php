<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeds a representative demo tenant with staff, patients, appointments,
 * consultations, prescriptions, and invoices. Intended for staging / QA
 * environments — run via `php artisan db:seed --class=DemoDataSeeder`.
 *
 * Idempotent: skips creation if a demo tenant with the known slug exists.
 */
class DemoDataSeeder extends Seeder
{
    private const DEMO_SLUG = 'demo-clinic';

    public function run(): void
    {
        if (Tenant::where('slug', self::DEMO_SLUG)->exists()) {
            $this->command->info('Demo data already seeded — skipping.');

            return;
        }

        $tenant = Tenant::factory()->smallClinic()->create([
            'name' => 'Klinic Demo Clinic',
            'slug' => self::DEMO_SLUG,
            'system' => 'AYURVEDA',
            'status' => 'ACTIVE',
            'trial_ends_at' => null,
        ]);

        // Activate an effective subscription so plan-gated features work.
        $smallClinicPlan = Plan::where('code', 'SMALL_CLINIC')->first()
            ?? Plan::factory()->create(['code' => 'SMALL_CLINIC']);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $smallClinicPlan->id,
            'status' => 'ACTIVE',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        app(TenantContext::class)->set($tenant->id);

        $this->seedStaff($tenant);
        $patients = $this->seedPatients($tenant);
        $this->seedAppointments($tenant, $patients);
        $this->seedConsultations($tenant, $patients);
        $this->seedInvoices($tenant, $patients);

        $this->command->info("Demo data seeded for tenant '{$tenant->name}' (slug: {$tenant->slug}).");
        $this->command->info('Login at the web UI with: doctor@demo.klinic360.com / password');
    }

    private function seedStaff(Tenant $tenant): void
    {
        User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create([
            'name' => 'Demo Owner',
            'email' => 'owner@demo.klinic360.com',
            'is_active' => true,
        ]);

        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create([
            'name' => 'Dr. Demo Physician',
            'email' => 'doctor@demo.klinic360.com',
            'is_active' => true,
        ]);
        $tenant->setRelation('demoDoctor', $doctor);

        User::factory()->forTenant($tenant)->role('RECEPTIONIST')->create([
            'name' => 'Demo Receptionist',
            'email' => 'reception@demo.klinic360.com',
            'is_active' => true,
        ]);
    }

    /**
     * @return Collection<int, Patient>
     */
    private function seedPatients(Tenant $tenant)
    {
        return Patient::factory()->count(25)->create(['tenant_id' => $tenant->id]);
    }

    private function seedAppointments(Tenant $tenant, $patients): void
    {
        $doctor = $tenant->getRelation('demoDoctor');

        foreach ($patients->take(15) as $patient) {
            Appointment::factory()->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'user_id' => $doctor->id,
                'created_by' => $doctor->id,
            ]);
        }

        // A handful of today's appointments for the dashboard.
        foreach ($patients->take(5) as $patient) {
            Appointment::factory()->forToday()->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'user_id' => $doctor->id,
                'created_by' => $doctor->id,
            ]);
        }
    }

    private function seedConsultations(Tenant $tenant, $patients): void
    {
        $doctor = $tenant->getRelation('demoDoctor');

        foreach ($patients->take(10) as $patient) {
            Consultation::factory()->completed()->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'user_id' => $doctor->id,
                'medicine_system' => $tenant->system,
            ]);
        }
    }

    private function seedInvoices(Tenant $tenant, $patients): void
    {
        $owner = $tenant->users()->where('role', 'CLINIC_OWNER')->first();

        foreach ($patients->take(10) as $patient) {
            $subtotal = fake()->numberBetween(50000, 300000); // ₹500–₹3000
            $total = (int) round($subtotal * 1.05);
            $issuedAt = Carbon::now()->subDays(fake()->numberBetween(1, 30));
            $method = fake()->randomElement(['CASH', 'UPI', 'CARD']);

            $invoice = Invoice::factory()->create([
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'status' => 'PAID',
                'source' => 'OPD',
                'subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'tax_cents' => $total - $subtotal,
                'total_cents' => $total,
                'amount_paid_cents' => $total,
                'amount_due_cents' => 0,
                'payment_mode' => $method,
                'issued_at' => $issuedAt,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Consultation & treatment',
                'type' => 'CONSULTATION',
                'reference_type' => 'App\\Models\\Consultation',
                'reference_id' => Consultation::where('patient_id', $patient->id)->value('id'),
                'quantity' => 1,
                'unit_price_cents' => $subtotal,
                'total_cents' => $subtotal,
            ]);

            Payment::factory()->create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'patient_id' => $patient->id,
                'method' => $method,
                'amount_cents' => $total,
                'currency' => 'INR',
                'status' => 'SUCCESS',
                'collected_by' => $owner?->id,
                'paid_at' => $issuedAt,
            ]);
        }
    }
}
