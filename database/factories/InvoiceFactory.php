<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'consultation_id' => null,
            'ipd_admission_id' => null,
            'invoice_number' => fake()->unique()->numerify('K360-INV-######'),
            'status' => 'DRAFT',
            'source' => 'OPD',
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
            'amount_paid_cents' => 0,
            'amount_due_cents' => 0,
            'currency' => 'INR',
            'payment_mode' => null,
            'notes' => null,
            'issued_at' => null,
            'voided_at' => null,
        ];
    }
}
