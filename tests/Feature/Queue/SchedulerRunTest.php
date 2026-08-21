<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Operational proof that `schedule:run` (the cron entry production uses)
 * actually evaluates every registered schedule and dispatches the
 * due commands without error.
 */
class SchedulerRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_run_executes_registered_commands_without_error(): void
    {
        $exitCode = Artisan::call('schedule:run');

        $this->assertSame(0, $exitCode, 'schedule:run failed');
    }

    public function test_schedule_lists_all_registered_commands(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach (['klinic:send-appointment-reminders', 'klinic:send-treatment-reminders', 'klinic:send-followup-reminders', 'klinic:retry-notifications', 'klinic:check-subscriptions', 'klinic:reconcile-payments', 'klinic:backup-database', 'klinic:cleanup'] as $command) {
            $this->assertStringContainsString($command, $output, "Missing scheduled command {$command}");
        }
    }
}
