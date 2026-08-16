<?php

declare(strict_types=1);

namespace App\Services\Treatments;

use App\Models\TreatmentBooking;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService as TreatmentServiceModel;
use App\Models\Therapist;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manages treatment bookings, sessions, and collision detection.
 *
 * Collision rules (enforced before persisting):
 *  - A therapist cannot have overlapping bookings on the same date.
 *  - A treatment room cannot have overlapping bookings on the same date.
 *  - Both checks run even if the resource is nullable (skipped when null).
 */
class TreatmentBookingService
{
    /**
     * Book a treatment session for a patient.
     *
     * @param array{patient_id:int, treatment_service_id:int, therapist_id?:?int, treatment_room_id?:?int, treatment_plan_id?:?int, treatment_package_id?:?int, booking_date:string|Carbon, start_time:string, end_time?:?string, payment_mode?:string} $attributes
     * @throws \App\Services\Treatments\BookingCollisionException
     */
    public function book(array $attributes): TreatmentBooking
    {
        $service = TreatmentServiceModel::findOrFail($attributes['treatment_service_id']);

        $startTime = $attributes['start_time'];
        $endTime = $attributes['end_time'] ?? $this->computeEndTime($service, $startTime);

        $this->guardCollisions(
            $attributes['therapist_id'] ?? null,
            $attributes['treatment_room_id'] ?? null,
            $attributes['booking_date'],
            $startTime,
            $endTime,
        );

        return DB::transaction(function () use ($attributes, $service, $startTime, $endTime) {
            $booking = TreatmentBooking::create([
                'patient_id' => $attributes['patient_id'],
                'treatment_service_id' => $service->id,
                'therapist_id' => $attributes['therapist_id'] ?? null,
                'treatment_room_id' => $attributes['treatment_room_id'] ?? null,
                'treatment_plan_id' => $attributes['treatment_plan_id'] ?? null,
                'treatment_package_id' => $attributes['treatment_package_id'] ?? null,
                'booking_date' => $attributes['booking_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'BOOKED',
                'payment_mode' => $attributes['payment_mode'] ?? 'PAY_AT_CLINIC',
            ]);

            return $booking->refresh();
        });
    }

    public function complete(TreatmentBooking $booking): TreatmentBooking
    {
        $booking->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        if ($booking->treatment_plan_id !== null) {
            $this->incrementPlanProgress($booking->treatment_plan_id);
        }

        return $booking->refresh();
    }

    public function cancel(TreatmentBooking $booking, ?string $reason = null): TreatmentBooking
    {
        $booking->update([
            'status' => 'CANCELLED',
        ]);

        return $booking->refresh();
    }

    /**
     * Get all bookings for a date (optionally filtered by therapist/room).
     *
     * @return Collection<int, TreatmentBooking>
     */
    public function forDate(Carbon $date, ?int $therapistId = null, ?int $roomId = null): Collection
    {
        return TreatmentBooking::whereDate('booking_date', $date)
            ->when($therapistId, fn ($q, $id) => $q->where('therapist_id', $id))
            ->when($roomId, fn ($q, $id) => $q->where('treatment_room_id', $id))
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Check for therapist/room collisions and throw if any overlap exists.
     */
    public function guardCollisions(
        ?int $therapistId,
        ?int $roomId,
        Carbon|string $bookingDate,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
    ): void {
        $date = $bookingDate instanceof Carbon ? $bookingDate->toDateString() : $bookingDate;

        if ($therapistId !== null) {
            $collision = TreatmentBooking::where('therapist_id', $therapistId)
                ->whereDate('booking_date', $date)
                ->where('status', '!=', 'CANCELLED')
                ->when($excludeBookingId, fn ($q, $id) => $q->where('id', '!=', $id))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->exists();

            if ($collision) {
                throw new BookingCollisionException('Therapist is already booked for this time slot.');
            }
        }

        if ($roomId !== null) {
            $collision = TreatmentBooking::where('treatment_room_id', $roomId)
                ->whereDate('booking_date', $date)
                ->where('status', '!=', 'CANCELLED')
                ->when($excludeBookingId, fn ($q, $id) => $q->where('id', '!=', $id))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->exists();

            if ($collision) {
                throw new BookingCollisionException('Treatment room is already booked for this time slot.');
            }
        }
    }

    public function hasTherapistCollision(int $therapistId, Carbon|string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        return TreatmentBooking::where('therapist_id', $therapistId)
            ->whereDate('booking_date', $dateStr)
            ->where('status', '!=', 'CANCELLED')
            ->when($excludeBookingId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->exists();
    }

    public function hasRoomCollision(int $roomId, Carbon|string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        return TreatmentBooking::where('treatment_room_id', $roomId)
            ->whereDate('booking_date', $dateStr)
            ->where('status', '!=', 'CANCELLED')
            ->when($excludeBookingId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->exists();
    }

    private function computeEndTime(TreatmentServiceModel $service, string $startTime): string
    {
        $start = Carbon::parse($startTime);

        return $start->addMinutes($service->duration_minutes)->format('H:i:s');
    }

    private function incrementPlanProgress(int $planId): void
    {
        \App\Models\TreatmentPlan::where('id', $planId)->increment('completed_sessions');

        $plan = \App\Models\TreatmentPlan::find($planId);
        if ($plan && $plan->completed_sessions >= $plan->total_sessions) {
            $plan->update(['status' => 'COMPLETED', 'ends_at' => now()]);
        }
    }
}
