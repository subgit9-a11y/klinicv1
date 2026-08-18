<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category' => fake()->randomElement(['RENT', 'UTILITIES', 'SALARIES', 'SUPPLIES', 'EQUIPMENT', 'MAINTENANCE', 'MARKETING', 'MISC']),
            'description' => fake()->sentence(),
            'amount_cents' => fake()->numberBetween(1000, 100000),
            'currency' => 'INR',
            'payment_method' => fake()->randomElement(['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE', 'OTHER']),
            'cash_register_id' => null,
            'created_by' => User::factory(),
            'expense_date' => fake()->date(),
            'receipt_path' => null,
        ];
    }
}
