<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Auth\RbacService;
use Illuminate\Database\Seeder;

/**
 * Mirrors the code-defined permission catalog into the DB-backed RBAC
 * tables so fresh installs start with DB as the source of truth.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(RbacService::class)->syncFromCode();
    }
}
