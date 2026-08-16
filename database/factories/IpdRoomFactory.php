<?php

namespace Database\Factories;

use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpdRoom>
 */
class IpdRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'ipd_ward_id' => IpdWard::factory(),
            'room_number' => fake()->unique()->numerify('R###'),
            'type' => fake()->optional()->randomElement(['general', 'private', 'icu']),
            'status' => 'AVAILABLE',
        ];
    }
}
