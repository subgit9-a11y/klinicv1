<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'invoice_id' => Invoice::factory(),
            'patient_id' => null,
            'payment_number' => fake()->unique()->numerify('K360-PAY-######'),
            'gateway' => 'MANUAL',
            'gateway_payment_id' => null,
            'gateway_order_id' => null,
            'method' => fake()->randomElement(['CASH', 'UPI', 'CARD']),
            'amount_cents' => fake()->numberBetween(10000, 500000),
            'currency' => 'INR',
            'status' => 'SUCCESS',
            'cheque_number' => null,
            'bank_name' => null,
            'notes' => null,
            'cash_register_id' => null,
            'collected_by' => null,
            'paid_at' => now(),
        ];
    }
}
