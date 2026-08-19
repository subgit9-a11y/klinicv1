<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash-drawer reconciliation: closing stores both the ledger-computed
 * (expected) balance — the existing closing_balance_cents — and the counted
 * (actual) cash, plus the variance between them. Nullable so registers
 * closed before a count is supplied remain valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $t) {
            $t->integer('actual_balance_cents')->nullable()->after('closing_balance_cents');
            $t->integer('variance_cents')->nullable()->after('actual_balance_cents')
                ->comment('actual - expected at close; negative = short');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $t) {
            $t->dropColumn(['actual_balance_cents', 'variance_cents']);
        });
    }
};
