<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operational health endpoints for load balancers / uptime monitors.
 * /health (liveness): app boots. /health/ready (readiness): DB reachable
 * AND the queue driver is usable AND the jobs tables exist — used as the
 * "is production actually serving work" probe.
 */
class HealthController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
    }

    public function ready(): JsonResponse
    {
        $checks = [];

        $checks['database'] = $this->probe('database', fn () => DB::select('select 1'));
        $checks['queue_tables'] = $this->probe('queue_tables', fn () => Schema::hasTable('jobs') && Schema::hasTable('failed_jobs'));

        $ok = collect($checks)->every(fn (array $c) => $c['ok']);

        return response()->json([
            'status' => $ok ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }

    /**
     * @return array{ok: bool, detail: ?string}
     */
    private function probe(string $name, callable $probe): array
    {
        try {
            $result = $probe();

            return ['ok' => (bool) ($result === true || ! empty($result)), 'detail' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
