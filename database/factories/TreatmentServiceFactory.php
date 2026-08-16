<?php

namespace Database\Factories;

use App\Models\TreatmentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentService>
 */
class TreatmentServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'name' => fake()->words(3, true),
            'category' => fake()->optional()->randomElement(['Panchakarma', 'Massage', 'Detox', 'Wellness']),
            'medicine_system' => fake()->optional()->randomElement(['Ayurveda', 'Siddha', 'Homeopathy']),
            'duration_minutes' => fake()->numberBetween(15, 90),
            'price_cents' => fake()->numberBetween(50000, 200000),
            'currency' => 'INR',
            'description' => fake()->optional()->sentence(),
            'requires_therapist' => true,
            'requires_room' => true,
            'is_active' => true,
        ];
    }
}
