<?php

namespace Database\Factories;

use App\Models\TreatmentBooking;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentBooking>
 */
class TreatmentBookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => \App\Models\Patient::factory(),
            'treatment_service_id' => \App\Models\TreatmentService::factory(),
            'therapist_id' => \App\Models\Therapist::factory(),
            'treatment_room_id' => \App\Models\TreatmentRoom::factory(),
            'treatment_plan_id' => null,
            'treatment_package_id' => null,
            'invoice_id' => null,
            'booking_date' => fake()->date(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => 'BOOKED',
            'payment_mode' => 'PAY_AT_CLINIC',
            'completed_at' => null,
        ];
    }
}
