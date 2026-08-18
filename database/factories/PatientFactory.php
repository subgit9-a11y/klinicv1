<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Services\Patients\PatientUidService;
use App\Services\Tenancy\TenantContext;
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
            // tenant_id is force-stamped by BelongsToTenant from TenantContext
            // when a tenant is active; left null here so the trait populates it.
            'tenant_id' => app(TenantContext::class)->id(),
            'k360_uid' => app(PatientUidService::class)->generate(),
            'first_name' => fake()->firstName($gender === 'OTHER' ? null : strtolower($gender)),
            'last_name' => fake()->lastName(),
            'phone' => function () {
                // Unique phone within a test run avoids the (tenant_id, phone)
                // unique constraint when the factory is called many times.
                return fake()->numerify('9#########');
            },
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

    /**
     * Ensure a globally-unique phone for the (tenant_id, phone) constraint.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Patient $patient) {
            if (empty($patient->phone)) {
                $patient->phone = fake()->unique()->numerify('9#########');
            }
        });
    }
}
