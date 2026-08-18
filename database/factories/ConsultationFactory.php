<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'user_id' => User::factory(),
            'medicine_system' => $this->faker->randomElement(['GENERAL', 'AYURVEDA', 'SIDDHA', 'HOMEOPATHY']),
            'consultation_type' => $this->faker->randomElement(['OPD', 'ONLINE', 'FOLLOW_UP', 'IPD']),
            'chief_complaint' => $this->faker->sentence,
            'history' => $this->faker->paragraph,
            'examination' => $this->faker->paragraph,
            'assessment' => $this->faker->paragraph,
            'diagnosis_summary' => $this->faker->sentence,
            'treatment_plan' => $this->faker->paragraph,
            'advice' => $this->faker->paragraph,
            'follow_up_instructions' => $this->faker->optional()->sentence,
            'follow_up_days' => $this->faker->optional()->numberBetween(1, 30),
            'status' => 'DRAFT',
            'system_specific' => null,
            'completed_at' => null,
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenantId,
            'patient_id' => Patient::factory()->create(['tenant_id' => $tenantId])->id,
            'user_id' => User::factory()->forTenant(Tenant::find($tenantId))->role('DOCTOR')->create()->id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);
    }
}
