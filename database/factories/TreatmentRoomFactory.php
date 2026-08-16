<?php

namespace Database\Factories;

use App\Models\TreatmentRoom;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentRoom>
 */
class TreatmentRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'room_number' => fake()->unique()->numerify('R###'),
            'type' => fake()->optional()->randomElement(['TREATMENT', 'PROCEDURE', 'RELAXATION']),
            'capacity' => 1,
            'supported_treatment_types' => null,
            'status' => 'AVAILABLE',
        ];
    }
}
