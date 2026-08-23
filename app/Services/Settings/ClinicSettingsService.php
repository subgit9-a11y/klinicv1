<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;

/**
 * Typed, DB-backed clinic configuration stored in tenant_settings.
 * Appointment types/durations and online-booking fee/duration become
 * per-clinic editable (Phase 23) instead of hard-coded config.
 */
class ClinicSettingsService
{
    public const KEY_APPOINTMENT_TYPES = 'appointments.types';
    public const KEY_ONLINE_FEE_CENTS = 'public_booking.consultation_fee_cents';
    public const KEY_ONLINE_DURATION_MINUTES = 'public_booking.consultation_duration_minutes';

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array<int, array{key: string, label: string, duration_minutes: int}>
     */
    public function appointmentTypes(): array
    {
        $raw = TenantSetting::get($this->requireTenant(), self::KEY_APPOINTMENT_TYPES);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return $this->defaultAppointmentTypes();
    }

    /**
     * @param  array<int, array{key: string, label: string, duration_minutes: int}>  $types
     */
    public function saveAppointmentTypes(array $types): void
    {
        $validated = Validator::make(['types' => $types], [
            'types' => ['required', 'array', 'min:1'],
            'types.*.key' => ['required', 'string', 'regex:/^[A-Z0-9_]+$/', 'max:32', 'distinct'],
            'types.*.label' => ['required', 'string', 'max:60'],
            'types.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ])->validate();

        $this->set(self::KEY_APPOINTMENT_TYPES, json_encode(array_values($validated['types'])), 'appointments');
    }

    public function onlineBookingFeeCents(?int $tenantId = null): int
    {
        $value = TenantSetting::get($tenantId ?? $this->requireTenant(), self::KEY_ONLINE_FEE_CENTS);

        return $value !== null ? (int) $value : (int) config('klinic.public_booking.consultation_fee_cents', 49900);
    }

    public function onlineBookingDurationMinutes(?int $tenantId = null): int
    {
        $value = TenantSetting::get($tenantId ?? $this->requireTenant(), self::KEY_ONLINE_DURATION_MINUTES);

        return $value !== null ? (int) $value : (int) config('klinic.public_booking.consultation_duration_minutes', 30);
    }

    public function saveOnlineBooking(int $feeCents, int $durationMinutes): void
    {
        Validator::make(
            ['fee_cents' => $feeCents, 'duration_minutes' => $durationMinutes],
            ['fee_cents' => ['required', 'integer', 'min:0'], 'duration_minutes' => ['required', 'integer', 'min:5', 'max:240']],
        )->validate();

        $this->set(self::KEY_ONLINE_FEE_CENTS, (string) $feeCents, 'public_booking');
        $this->set(self::KEY_ONLINE_DURATION_MINUTES, (string) $durationMinutes, 'public_booking');
    }

    /**
     * @return array<int, array{key: string, label: string, duration_minutes: int}>
     */
    public function defaultAppointmentTypes(): array
    {
        return [
            ['key' => 'CONSULTATION', 'label' => 'Consultation', 'duration_minutes' => 30],
            ['key' => 'FOLLOW_UP', 'label' => 'Follow-up', 'duration_minutes' => 15],
            ['key' => 'TELECONSULT', 'label' => 'Teleconsultation', 'duration_minutes' => 30],
        ];
    }

    private function set(string $key, string $value, string $category): void
    {
        TenantSetting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->requireTenant(), 'key' => $key],
            ['value' => $value, 'category' => $category],
        );
    }

    private function requireTenant(): int
    {
        $id = $this->tenant->id();
        abort_unless($id, 500, 'Tenant context is not set.');

        return $id;
    }
}
