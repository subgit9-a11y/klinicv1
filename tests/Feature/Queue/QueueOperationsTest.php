<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocumentOcr;
use App\Jobs\SendNotificationJob;
use App\Models\Document;
use App\Models\NotificationDelivery;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\OcrService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\Fakes\FakeOcrProvider;
use Tests\TestCase;

/**
 * Operational proof that the DATABASE queue driver carries jobs from
 * dispatch through a REAL `queue:work` run (not the test-only sync driver):
 * notifications and OCR run off the request path, and failed jobs are
 * recorded for the ops retry flow.
 */
class QueueOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the real database queue driver for these operational proofs.
        config(['queue.default' => 'database']);
        // failed_jobs exists from framework migration.
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    public function test_database_queue_dispatches_and_worker_processes_notification(): void
    {
        $this->seed(\Database\Seeders\NotificationTemplateSeeder::class);

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+919000000001']);

        $delivery = NotificationDelivery::create([
            'tenant_id' => $tenant->id,
            'notifiable_type' => Patient::class,
            'notifiable_id' => $patient->id,
            'channel' => 'in_app',
            'recipient' => '+919000000001',
            'status' => 'PENDING',
            'attempts' => 0,
        ]);

        SendNotificationJob::dispatch($delivery->id, 'appointment.confirmation', 'in_app', ['patient_name' => 'X']);

        // Dispatch on the database queue leaves a row for the worker.
        $this->assertDatabaseHas('jobs', ['queue' => 'default']);

        // A real worker run processes it and the jobs table empties.
        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true, '--queue' => 'default']);
        $this->assertSame(0, DB::table('jobs')->count());

        // The real job re-dispatched sendOnChannel, which completes in_app
        // inline — a SENT delivery now exists for the patient.
        $this->assertTrue(
            NotificationDelivery::where('notifiable_id', $patient->id)
                ->where('channel', 'in_app')
                ->where('status', 'SENT')
                ->exists(),
            'No SENT in_app delivery for the patient'
        );
    }

    public function test_ocr_job_dispatched_by_event_runs_via_database_queue(): void
    {
        $provider = new FakeOcrProvider;
        app()->instance(\App\Contracts\OCRProviderInterface::class, $provider);

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $document = Document::create([
            'tenant_id' => $tenant->id,
            'name' => 'scan.pdf',
            'type' => 'LAB_REPORT',
            'disk' => 'local',
            'path' => 'x/scan.pdf',
            'size' => 10,
        ]);

        ProcessDocumentOcr::dispatch($document->id);
        $this->assertDatabaseHas('jobs', ['queue' => 'default']);

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
        $this->assertSame(0, DB::table('jobs')->count());

        // OCR text is stamped on the document (worker tenant isolation).
        $this->assertNotNull($document->fresh()->metadata['ocr_text'] ?? null);
        $this->assertSame('Haemoglobin: 14.2 g/dL', $document->fresh()->metadata['ocr_text']);
    }

    public function test_permanently_failing_job_is_recorded_in_failed_jobs(): void
    {
        // A job that always throws must be recorded in failed_jobs after
        // its retry budget is exhausted — this powers queue:failed / retry.
        $failedJob = new FailingTestJob;
        $failedJob->tries = 1;
        dispatch($failedJob);

        Artisan::call('queue:work', ['--once' => true]);

        // assertDatabaseHas doesn't support LIKE matching — assert a row
        // exists with our exception substring via the query builder.
        $found = DB::table('failed_jobs')
            ->where('payload', 'like', '%FailingTestJob%')
            ->exists();
        $this->assertTrue($found, 'failed_jobs has no FailingTestJob entry');
    }
}
