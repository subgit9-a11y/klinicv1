<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['MALE', 'FEMALE', 'OTHER']);

        return [
            'k360_uid' => 'K360-P-'.str_pad((string) fake()->unique()->randomNumber(7), 10, '0', STR_PAD_LEFT),
            'first_name' => fake()->firstName($gender === 'OTHER' ? null : strtolower($gender)),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->optional()->safeEmail(),
            'gender' => $gender,
            'dob' => fake()->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'blood_group' => fake()->optional()->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'address' => fake()->optional()->streetAddress(),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->state(),
            'pincode' => fake()->optional()->numerify('######'),
            'country_code' => 'IN',
            'status' => 'ACTIVE',
        ];
    }
}
