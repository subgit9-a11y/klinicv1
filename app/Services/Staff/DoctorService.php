<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\DoctorAvailability;
use App\Models\DoctorLeave;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Doctor onboarding and availability management. Clinic Owners onboard
 * doctors (and other clinical staff) and manage their weekly availability
 * schedule. All operations are tenant-scoped.
 */
class DoctorService
{
    public const DAYS = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

    /**
     * @return Collection<int, User>
     */
    public function listDoctors(): Collection
    {
        return User::role('DOCTOR')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?User
    {
        return User::role('DOCTOR')->find($id);
    }

    /**
     * Onboard a new doctor (or clinical staff member) into the resolved tenant.
     *
     * @param  array{name:string,email:string,phone?:?string,password:string,role?:string,specialization?:?string,registration_number?:?string,medicine_system?:?string,consultation_fee_cents?:?int,followup_fee_cents?:?int}  $attributes
     */
    public function onboard(array $attributes): User
    {
        $tenantId = $this->requireTenant();
        $validated = Validator::validate($attributes, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'in:DOCTOR,THERAPIST,NURSE,RECEPTIONIST,IPD_STAFF,ASSISTANT'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'consultation_fee_cents' => ['nullable', 'integer', 'min:0'],
            'followup_fee_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated, $tenantId) {
            return User::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'] ?? 'DOCTOR',
                'specialization' => $validated['specialization'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'medicine_system' => $validated['medicine_system'] ?? null,
                'consultation_fee_cents' => $validated['consultation_fee_cents'] ?? null,
                'followup_fee_cents' => $validated['followup_fee_cents'] ?? null,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{name?:string,phone?:?string,specialization?:?string,registration_number?:?string,medicine_system?:?string,consultation_fee_cents?:?int,followup_fee_cents?:?int,is_active?:bool,password?:string}  $attributes
     */
    public function updateProfile(User $doctor, array $attributes): User
    {
        $this->assertSameTenant($doctor);

        $validated = Validator::make($attributes, [
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'consultation_fee_cents' => ['nullable', 'integer', 'min:0'],
            'followup_fee_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:8'],
        ])->validate();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $doctor->update($validated);

        return $doctor->refresh();
    }

    /**
     * Replace a doctor's weekly availability schedule (deletes old, inserts new).
     *
     * @param  array<array{day_of_week:string,start_time:string,end_time:string,break_start_time?:?string,break_end_time?:?string}>  $slots
     * @return Collection<int, DoctorAvailability>
     */
    public function setAvailability(User $doctor, array $slots): Collection
    {
        $this->assertSameTenant($doctor);
        $tenantId = $this->requireTenant();

        foreach ($slots as $slot) {
            Validator::validate($slot, [
                'day_of_week' => ['required', 'in:'.implode(',', self::DAYS)],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
                'break_start_time' => ['nullable', 'date_format:H:i'],
                'break_end_time' => ['nullable', 'date_format:H:i', 'after:break_start_time'],
            ]);
        }

        return DB::transaction(function () use ($doctor, $slots, $tenantId) {
            DoctorAvailability::where('user_id', $doctor->id)->delete();

            $rows = [];
            $now = now();
            foreach ($slots as $slot) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'user_id' => $doctor->id,
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'break_start_time' => $slot['break_start_time'] ?? null,
                    'break_end_time' => $slot['break_end_time'] ?? null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows) {
                DoctorAvailability::insert($rows);
            }

            return $doctor->availability()->orderBy('day_of_week')->get();
        });
    }

    /**
     * Record dated leave (or holiday) for a doctor over an inclusive date range.
     * Slots are not generated for dates covered by approved leave.
     *
     * @param  array{start_date:string,end_date:string,reason?:?string,type?:string,is_approved?:bool}  $attributes
     */
    public function setLeave(User $doctor, array $attributes): DoctorLeave
    {
        $this->assertSameTenant($doctor);
        $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:LEAVE,HOLIDAY,EMERGENCY,OTHER'],
            'is_approved' => ['boolean'],
        ]);

        return DoctorLeave::create([
            'tenant_id' => $doctor->tenant_id,
            'user_id' => $doctor->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'type' => $validated['type'] ?? 'LEAVE',
            'is_approved' => $validated['is_approved'] ?? true,
        ]);
    }

    public function getAvailability(User $doctor): Collection
    {
        $this->assertSameTenant($doctor);

        return $doctor->availability()->orderBy('day_of_week')->get();
    }

    private function requireTenant(): int
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => 'No tenant context resolved.']);
        }

        return $tenantId;
    }

    private function assertSameTenant(User $user): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $user->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['user' => 'User belongs to a different tenant.']);
        }
    }
}
