<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'consultation_id' => null,
            'user_id' => null,
            'status' => 'ACTIVE',
            'notes' => fake()->optional()->sentence(),
            'issued_at' => now(),
        ];
    }

    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Prescription $prescription) use ($count) {
            PrescriptionItem::factory()->count($count)->create([
                'prescription_id' => $prescription->id,
            ]);
        });
    }
}
