<?php

namespace Database\Factories;

use App\Models\PaymentWebhook;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhook>
 */
class PaymentWebhookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'gateway' => 'CASHFREE',
            'event_id' => fake()->unique()->numerify('evt-####'),
            'event_type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'gateway_order_id' => fake()->numerify('cf-####'),
            'gateway_payment_id' => fake()->numerify('pay-####'),
            'payload' => [],
            'processed' => false,
            'processed_at' => null,
        ];
    }
}
