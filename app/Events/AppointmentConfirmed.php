<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an appointment is confirmed (e.g. payment verified for an online
 * booking). Listeners dispatch a confirmation notification job.
 */
class AppointmentConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        $appt = $this->appointment;
        $date = $appt->appointment_date instanceof \Illuminate\Support\Carbon
            ? $appt->appointment_date->format('Y-m-d')
            : (string) $appt->appointment_date;

        return [
            'patient_name' => $appt->patient?->name ?? '',
            'doctor_name' => $appt->doctor?->name ?? '',
            'appointment_date' => $date,
            'start_time' => $appt->start_time,
            'reference' => (string) $appt->id,
            'reason' => $this->reason ?? '',
        ];
    }
}
