<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Events\AppointmentBooked;
use App\Events\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AppointmentToken;
use App\Models\DoctorLeave;
use App\Models\Patient;
use App\Models\TokenSequence;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Unified appointment engine covering WALK_IN, IN_PERSON, ONLINE,
 * FOLLOW_UP, TREATMENT, and IPD_REVIEW appointments.
 *
 * Double-booking is prevented via a SELECT ... FOR UPDATE lock on the
 * doctor's overlapping appointments inside a serialized transaction. A
 * generated exclusion constraint is intentionally not relied upon because
 * end_time is derived from start_time + duration_minutes and overlap
 * detection across ranges is checked in code under a row lock.
 */
class AppointmentService
{
    private const TYPES = ['WALK_IN', 'IN_PERSON', 'ONLINE', 'FOLLOW_UP', 'TREATMENT', 'IPD_REVIEW'];

    private const STATUS_FLOW = [
        'SCHEDULED' => ['CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'NO_SHOW'],
        'CONFIRMED' => ['CHECKED_IN', 'CANCELLED', 'NO_SHOW'],
        'CHECKED_IN' => ['IN_CONSULTATION', 'CANCELLED', 'NO_SHOW'],
        'IN_CONSULTATION' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
        'NO_SHOW' => [],
    ];

    public function book(array $attributes, User $creator): Appointment
    {
        $tenantId = $this->requireTenant();
        $validated = $this->validateBooking($attributes);

        $appointment = DB::transaction(function () use ($validated, $creator, $tenantId) {
            $doctorId = $validated['user_id'] ?? null;

            if ($doctorId !== null) {
                // Lock the doctor's overlapping appointments for this date.
                $this->assertNoCollision(
                    $tenantId,
                    $doctorId,
                    $validated['appointment_date'],
                    $validated['start_time'],
                    $validated['end_time'],
                    excludeId: null
                );
            }

            $appointment = Appointment::create([
                'tenant_id' => $tenantId,
                'patient_id' => $validated['patient_id'],
                'user_id' => $doctorId,
                'created_by' => $creator->id,
                'type' => $validated['type'],
                'status' => $validated['status'] ?? 'SCHEDULED',
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'duration_minutes' => $validated['duration_minutes'],
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->recordHistory($appointment, $appointment->status, $creator, 'Appointment created');

            // Walk-in appointments receive a queue token automatically.
            if ($appointment->type === 'WALK_IN') {
                $this->issueToken($appointment, $creator);
            }

            return $appointment->fresh();
        });

        // Dispatch after commit so listeners only fire on a persisted booking.
        Event::dispatch(new AppointmentBooked($appointment));

        return $appointment;
    }

    public function reschedule(Appointment $appointment, array $attributes, User $by): Appointment
    {
        $validated = $this->validateReschedule($attributes, $appointment);

        return DB::transaction(function () use ($appointment, $validated, $by) {
            if ($appointment->user_id !== null) {
                $this->assertNoCollision(
                    $appointment->tenant_id,
                    $appointment->user_id,
                    $validated['appointment_date'],
                    $validated['start_time'],
                    $validated['end_time'],
                    excludeId: $appointment->id
                );
            }

            $appointment->update([
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'duration_minutes' => $validated['duration_minutes'],
            ]);

            $this->recordHistory($appointment, $appointment->status, $by, 'Rescheduled to '.$validated['appointment_date'].' '.$validated['start_time']);

            return $appointment->fresh();
        });
    }

    public function changeStatus(Appointment $appointment, string $status, User $by, ?string $note = null): Appointment
    {
        $current = $appointment->status;

        if (! isset(self::STATUS_FLOW[$current])) {
            throw ValidationException::withMessages(['status' => "Unknown current status: {$current}"]);
        }

        if (! in_array($status, self::STATUS_FLOW[$current], true) && $status !== $current) {
            throw ValidationException::withMessages([
                'status' => "Invalid transition from {$current} to {$status}.",
            ]);
        }

        $appointment = DB::transaction(function () use ($appointment, $status, $by, $note, $current) {
            $updates = ['status' => $status];

            match ($status) {
                'CHECKED_IN' => $updates['checked_in_at'] = now(),
                'COMPLETED' => $updates['completed_at'] = now(),
                'CANCELLED' => $updates['cancelled_at'] = now(),
                default => null,
            };

            $appointment->update($updates);

            $this->recordHistory($appointment, $status, $by, $note ?: "Status: {$current} → {$status}");

            if ($status === 'CHECKED_IN' && $appointment->token) {
                $appointment->token()->update(['status' => 'CALLED', 'called_at' => now()]);
            }
            if ($status === 'IN_CONSULTATION' && $appointment->token) {
                $appointment->token()->update(['status' => 'IN_PROGRESS']);
            }
            if ($status === 'COMPLETED' && $appointment->token) {
                $appointment->token()->update(['status' => 'DONE']);
            }
            if ($status === 'NO_SHOW' && $appointment->token) {
                $appointment->token()->update(['status' => 'SKIPPED']);
            }

            return $appointment->fresh();
        });

        // Dispatch after commit so confirmation notifications only fire on a
        // persisted transition.
        if ($status === 'CONFIRMED' && $current !== 'CONFIRMED') {
            Event::dispatch(new AppointmentConfirmed($appointment, $note));
        }

        return $appointment;
    }

    public function cancel(Appointment $appointment, User $by, ?string $reason = null): Appointment
    {
        if (in_array($appointment->status, ['COMPLETED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Cannot cancel an appointment that is already '.$appointment->status.'.',
            ]);
        }

        return DB::transaction(function () use ($appointment, $by, $reason) {
            $appointment->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->recordHistory($appointment, 'CANCELLED', $by, $reason);

            return $appointment->fresh();
        });
    }

    /**
     * Compute available slots for a doctor on a given date, minus already-booked overlaps.
     *
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function availableSlots(User $doctor, string $date, int $slotMinutes = 15): array
    {
        // Honour per-doctor consultation duration when set; callers passing an
        // explicit slotMinutes (e.g. 15-min grid for the board) still override.
        $slotMinutes = $doctor->consultation_duration_minutes
            ? $doctor->consultationDurationMinutes()
            : $slotMinutes;

        $dayCode = $this->dayOfWeekCode($date);

        // Approved leave/holiday over this date → no bookable slots.
        if (DoctorLeave::onDate($date)->where('user_id', $doctor->id)->exists()) {
            return [];
        }

        $availability = $doctor->availability()
            ->where('day_of_week', $dayCode)
            ->where('is_active', true)
            ->get();

        if ($availability->isEmpty()) {
            return [];
        }

        $dayStart = now()->parse($date)->startOfDay();
        $dayEnd = now()->parse($date)->endOfDay();

        $booked = Appointment::where('tenant_id', $doctor->tenant_id)
            ->where('user_id', $doctor->id)
            ->whereBetween('appointment_date', [$dayStart, $dayEnd])
            ->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
            ->get(['start_time', 'end_time']);

        // Per-doctor daily capacity cap: once met, no further slots are offered.
        $maxDaily = $doctor->max_daily_appointments;
        $atCapacity = $maxDaily !== null && $booked->count() >= $maxDaily;

        $slots = [];
        foreach ($availability as $block) {
            $cursor = strtotime($block->start_time);
            $end = strtotime($block->end_time);
            $breakStart = $block->break_start_time ? strtotime($block->break_start_time) : null;
            $breakEnd = $block->break_end_time ? strtotime($block->break_end_time) : null;

            while ($cursor + $slotMinutes * 60 <= $end) {
                $slotStart = $cursor;
                $slotEnd = $cursor + $slotMinutes * 60;
                $slotStartStr = date('H:i', $slotStart);
                $slotEndStr = date('H:i', $slotEnd);

                // Skip any slot that overlaps the intra-day break window.
                $inBreak = $breakStart !== null && $breakEnd !== null
                    && $slotStart < $breakEnd && $slotEnd > $breakStart;

                if ($inBreak) {
                    // Jump past the break instead of inching forward one slot
                    // at a time — the whole window is unavailable.
                    $cursor = $breakEnd;
                    continue;
                }

                $overlap = $booked->contains(function ($b) use ($slotStartStr, $slotEndStr) {
                    return $b->start_time < $slotEndStr && $b->end_time > $slotStartStr;
                });
                $slots[] = [
                    'start' => $slotStartStr,
                    'end' => $slotEndStr,
                    'available' => ! $overlap && ! $atCapacity,
                ];
                $cursor += $slotMinutes * 60;
            }
        }

        return $slots;
    }

    public function todaysAppointments(?int $doctorId = null): Collection
    {
        $query = Appointment::query()
            ->where('appointment_date', today())
            ->whereNotIn('status', ['CANCELLED'])
            ->orderBy('start_time');

        if ($doctorId !== null) {
            $query->where('user_id', $doctorId);
        }

        return $query->limit(100)->get();
    }

    public function forPatient(Patient $patient): Collection
    {
        return Appointment::where('patient_id', $patient->id)
            ->orderByDesc('appointment_date')
            ->orderBy('start_time')
            ->get();
    }

    protected function assertNoCollision(int $tenantId, int $doctorId, string $date, string $start, string $end, ?int $excludeId): void
    {
        // Deterministic serialization: lock the doctor's row so that two
        // concurrent bookings for the same doctor cannot both pass the
        // "no overlapping appointment exists" check when neither appointment
        // has been inserted yet. lockForUpdate() on an existing users row is
        // portable across SQLite (dev) and MySQL/MariaDB (prod); advisory
        // GET_LOCK is MySQL-only.
        User::whereKey($doctorId)->lockForUpdate()->first();

        $dayStart = now()->parse($date)->startOfDay();
        $dayEnd = now()->parse($date)->endOfDay();

        $query = Appointment::where('tenant_id', $tenantId)
            ->where('user_id', $doctorId)
            ->whereBetween('appointment_date', [$dayStart, $dayEnd])
            ->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->lockForUpdate()->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_time' => 'The selected slot overlaps with an existing appointment for this doctor.',
            ]);
        }
    }

    protected function issueToken(Appointment $appointment, User $creator): AppointmentToken
    {
        $tenantId = $appointment->tenant_id;

        // Per-doctor per-day counter row — locked FOR UPDATE before the number
        // is consumed. Unlike locking the day's tokens (which locks nothing
        // when no token exists yet), the counter row exists from the first
        // walk-in, so even two simultaneous first-of-day bookings serialize.
        $tokenNumber = TokenSequence::lockFor(
            $tenantId,
            $appointment->user_id,
            $appointment->appointment_date->toDateString(),
        )->consume();

        return AppointmentToken::create([
            'tenant_id' => $tenantId,
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'token_number' => $tokenNumber,
            'appointment_date' => $appointment->appointment_date,
            'status' => 'WAITING',
        ]);
    }

    protected function recordHistory(Appointment $appointment, string $status, User $by, ?string $note): AppointmentStatusHistory
    {
        return AppointmentStatusHistory::create([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
            'status' => $status,
            'changed_by' => $by->id,
            'note' => $note,
        ]);
    }

    protected function validateBooking(array $attributes): array
    {
        $validator = Validator::make($attributes, [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'status' => ['nullable', 'in:SCHEDULED,CONFIRMED'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $validator->validated();
        $data['end_time'] = $this->computeEndTime($data['start_time'], $data['duration_minutes'] ?? 15);
        $data['duration_minutes'] = $data['duration_minutes'] ?? 15;

        return $data;
    }

    protected function validateReschedule(array $attributes, Appointment $appointment): array
    {
        $validator = Validator::make($attributes, [
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'between:5,480'],
        ]);

        $data = $validator->validated();
        $data['end_time'] = $this->computeEndTime($data['start_time'], $data['duration_minutes'] ?? $appointment->duration_minutes);
        $data['duration_minutes'] = $data['duration_minutes'] ?? $appointment->duration_minutes;

        return $data;
    }

    protected function computeEndTime(string $startTime, int $durationMinutes): string
    {
        return date('H:i', strtotime($startTime) + $durationMinutes * 60);
    }

    protected function requireTenant(): int
    {
        $id = app(TenantContext::class)->id();
        if ($id === null) {
            throw new \RuntimeException('Cannot book an appointment without a tenant context.');
        }

        return $id;
    }

    protected function dayOfWeekCode(string $date): string
    {
        return ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][(int) date('w', strtotime($date))];
    }
}
