<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Test double: an always-failing job to prove failed_jobs recording. */
class FailingTestJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        throw new \RuntimeException('boom');
    }
}
