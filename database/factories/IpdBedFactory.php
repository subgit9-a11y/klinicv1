<?php

namespace Database\Factories;

use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpdBed>
 */
class IpdBedFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'ipd_room_id' => IpdRoom::factory(),
            'bed_number' => fake()->unique()->numerify('B##'),
            'status' => 'AVAILABLE',
            'daily_rate_cents' => fake()->numberBetween(100000, 500000),
        ];
    }
}
