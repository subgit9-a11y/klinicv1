<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Register '.fake()->word(),
            'user_id' => User::factory(),
            'status' => 'OPEN',
            'opening_balance_cents' => fake()->numberBetween(0, 50000),
            'closing_balance_cents' => 0,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status' => 'CLOSED',
            'closing_balance_cents' => fake()->numberBetween(0, 100000),
            'closed_at' => now(),
        ]);
    }
}
