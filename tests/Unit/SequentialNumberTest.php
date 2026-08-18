<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SequentialNumber;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the concurrency-safe sequential-number generator produces
 * unique, monotonically-incrementing K360-* numbers and never collides
 * with previously-issued numbers — replacing the racy `MAX(id)+1` pattern.
 */
class SequentialNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Tenancy scope requires a real tenant row for FK references.
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_next_produces_zero_padded_first_number_on_empty_table(): void
    {
        $number = SequentialNumber::next('invoices', 'K360-INV', 'invoice_number');

        $this->assertSame('K360-INV-000001', $number);
    }

    public function test_next_increments_beyond_existing_numbers(): void
    {
        // Seed two existing invoice numbers (out of order to also exercise the MAX scan).
        Invoice::factory()->create(['invoice_number' => 'K360-INV-000007']);
        Invoice::factory()->create(['invoice_number' => 'K360-INV-000003']);

        $number = SequentialNumber::next('invoices', 'K360-INV', 'invoice_number');

        $this->assertSame('K360-INV-000008', $number);
    }

    public function test_next_never_returns_a_number_that_already_exists(): void
    {
        Invoice::factory()->create(['invoice_number' => 'K360-INV-000001']);

        // Each generated number is persisted (as the real BillingService does),
        // so the next call must advance past it and never collide.
        $issued = [];
        for ($i = 0; $i < 5; $i++) {
            $n = SequentialNumber::next('invoices', 'K360-INV', 'invoice_number');
            $this->assertNotContains($n, $issued, "Duplicate number generated: $n");
            $issued[] = $n;
            Invoice::factory()->create(['invoice_number' => $n]);
        }

        $this->assertCount(5, array_unique($issued));
    }
}
