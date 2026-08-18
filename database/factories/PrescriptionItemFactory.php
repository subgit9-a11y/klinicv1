<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    public function definition(): array
    {
        $forms = ['tablet', 'syrup', 'churna', 'kwath', 'oil', 'capsule'];

        return [
            'prescription_id' => Prescription::factory(),
            'medicine' => fake()->word().' '.fake()->word(),
            'form' => fake()->randomElement($forms),
            'strength' => fake()->optional()->numerify('###mg'),
            'dose' => fake()->optional()->numerify('#-#'),
            'frequency' => fake()->optional()->randomElement(['BD', 'TDS', 'HS', 'OD']),
            'duration' => fake()->optional()->numerify('# days'),
            'route' => fake()->optional()->randomElement(['oral', 'topical', 'inhalation']),
            'quantity' => fake()->optional()->numerify('##'),
            'instructions' => fake()->optional()->sentence(),
            'timing' => fake()->optional()->randomElement(['before food', 'after food', 'empty stomach']),
            'anupana' => fake()->optional()->randomElement(['warm water', 'honey', 'ghee']),
            'external_application' => false,
        ];
    }
}
