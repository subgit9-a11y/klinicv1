<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\CashRegisterEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegisterEntry>
 */
class CashRegisterEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cash_register_id' => CashRegister::factory(),
            'reference_type' => null,
            'reference_id' => null,
            'type' => fake()->randomElement(['CREDIT', 'DEBIT']),
            'method' => fake()->randomElement(['CASH', 'UPI', 'CARD']),
            'amount_cents' => fake()->numberBetween(100, 50000),
            'currency' => 'INR',
            'description' => fake()->sentence(),
            'user_id' => null,
        ];
    }
}
