<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'ACTIVE',
            'gateway_subscription_id' => null,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'cancelled_at' => null,
            'cancel_reason' => null,
            'metadata' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancel_reason' => 'Test cancellation',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'EXPIRED',
            'ends_at' => now()->subDay(),
        ]);
    }
}
