<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Production-grade ops proofs: security headers, health probes, backup
 * command contract.
 */
class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_present_on_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_health_returns_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_health_ready_reports_database_and_queue_tables(): void
    {
        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.queue_tables.ok', true);
    }

    public function test_backup_command_fails_loudly_on_non_mysql(): void
    {
        // On the sqlite test driver the backup command must abort (never
        // a silent no-op that looks like a success).
        $exit = Artisan::call('klinic:backup-database');

        $this->assertSame(1, $exit);
    }
}
