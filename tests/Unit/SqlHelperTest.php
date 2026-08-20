<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use App\Support\Sql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the driver-aware person-name concat expression used by patient
 * search (PatientService + Livewire boards) is correct per driver and that
 * the produced query actually filters patients on the active connection.
 */
class SqlHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_uses_double_pipe_concat(): void
    {
        $this->assertSame('first_name || " " || last_name', Sql::personNameConcat('sqlite'));
    }

    public function test_mysql_uses_concat_with_coalesce(): void
    {
        $this->assertSame('CONCAT(first_name, " ", COALESCE(last_name, ""))', Sql::personNameConcat('mysql'));
        $this->assertSame(Sql::personNameConcat('mysql'), Sql::personNameConcat('mariadb'));
    }

    public function test_default_driver_resolves_from_connection(): void
    {
        // Driver-agnostic: whatever the current suite connection is, the
        // no-arg call must match the explicit-driver call for it.
        $driver = config('database.connections.'.config('database.default').'.driver');
        $this->assertSame(Sql::personNameConcat($driver), Sql::personNameConcat());
    }

    public function test_name_concat_search_matches_full_name(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        Patient::factory()->create(['first_name' => 'Ananya', 'last_name' => 'Sharma']);
        Patient::factory()->create(['first_name' => 'Rohan', 'last_name' => 'Verma']);

        $matches = Patient::query()
            ->whereRaw('lower('.Sql::personNameConcat().') like ?', ['%ananya sha%'])
            ->pluck('first_name');

        $this->assertSame(['Ananya'], $matches->all());
    }
}
