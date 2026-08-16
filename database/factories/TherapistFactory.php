<?php

namespace Database\Factories;

use App\Models\Therapist;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Therapist>
 */
class TherapistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'user_id' => null,
            'name' => fake()->name(),
            'qualification' => fake()->optional()->words(2, true),
            'specialty' => fake()->optional()->randomElement(['Panchakarma', 'Massage', 'Siddha', 'Homeopathy']),
            'services' => null,
            'is_active' => true,
        ];
    }
}
