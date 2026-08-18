<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'klinic:send-appointment-reminders';

    protected $description = 'Send reminders for appointments scheduled within the next 24 hours';

    public function handle(NotificationService $notifications): int
    {
        $windowStart = Carbon::now();
        $windowEnd = Carbon::now()->addDay();

        $appointments = Appointment::query()
            ->where('status', 'SCHEDULED')
            ->whereBetween('appointment_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->with('patient')
            ->limit(500)
            ->get();

        $sent = 0;
        foreach ($appointments as $appointment) {
            $date = $appointment->appointment_date instanceof Carbon
                ? $appointment->appointment_date
                : Carbon::parse((string) $appointment->appointment_date);
            $when = $date->copy()->setTimeFromTimeString($appointment->start_time);
            // Only remind for appointments due within the next 24h.
            if ($when->isFuture() && $when->lessThanOrEqualTo($windowEnd)) {
                $notifications->send(
                    $appointment->patient,
                    'appointment.reminder',
                    ['appointment_date' => $appointment->appointment_date->format('Y-m-d'), 'start_time' => $appointment->start_time],
                    ['in_app']
                );
                $sent++;
            }
        }

        $this->info("Sent {$sent} appointment reminders.");

        return self::SUCCESS;
    }
}
