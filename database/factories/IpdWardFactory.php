<?php

namespace Database\Factories;

use App\Models\IpdWard;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpdWard>
 */
class IpdWardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['general', 'private', 'icu']),
            'is_active' => true,
        ];
    }
}
