<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrder>
 */
class PaymentOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'internal_order_id' => fake()->unique()->numerify('K360-ORD-######'),
            'gateway' => 'CASHFREE',
            'gateway_order_id' => fake()->unique()->numerify('cf-####'),
            'payable_type' => Invoice::class,
            'payable_id' => Invoice::factory(),
            'amount_cents' => fake()->numberBetween(10000, 500000),
            'currency' => 'INR',
            'customer_email' => fake()->optional()->safeEmail(),
            'customer_phone' => fake()->optional()->numerify('9#########'),
            'status' => 'PENDING',
            'metadata' => null,
        ];
    }
}
