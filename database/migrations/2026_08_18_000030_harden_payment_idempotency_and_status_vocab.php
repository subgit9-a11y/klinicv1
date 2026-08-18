<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardens payment idempotency at the database level and aligns the payment
 * status vocabulary with the canonical state machine used by BillingService
 * (PENDING / SUCCESS / FAILED / REFUNDED).
 *
 * 1. Unique constraint on payments(gateway, gateway_payment_id) so a gateway
 *    payment can never be recorded twice even under a concurrent race that
 *    slips past the service-level dedup. gateway_payment_id is nullable, and
 *    both SQLite and MySQL allow multiple NULLs in a unique index, so manual
 *    (non-gateway) payments are unaffected.
 *
 * 2. The payments.status column was declared with default 'COMPLETED' and a
 *    comment listing PENDING/COMPLETED/FAILED/REFUNDED, but BillingService
 *    records SUCCESS and ReportService sums on SUCCESS. Standardize the
 *    column default + comment on SUCCESS and the canonical vocab so the
 *    schema, service, and reports agree (addresses review item #11).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop any prior non-unique index on gateway_payment_id so the
        // composite unique index replaces it cleanly (wrap in try/catch —
        // index naming differs across drivers / may not exist).
        try {
            Schema::table('payments', function (Blueprint $t) {
                $t->dropIndex(['gateway_payment_id']);
            });
        } catch (\Throwable) {
            // Index may not exist or name may differ — ignore.
        }

        // Add the composite unique constraint. On a fresh install the base
        // migration already declares it, so guard against "already exists".
        try {
            Schema::table('payments', function (Blueprint $t) {
                $t->unique(['gateway', 'gateway_payment_id'], 'payments_gateway_payment_unique');
            });
        } catch (\Throwable) {
            // Constraint already present — ignore.
        }

        // Align the status default + comment with the canonical vocab
        // (BillingService writes SUCCESS; the old default was COMPLETED).
        Schema::table('payments', function (Blueprint $t) {
            $t->string('status', 24)->default('SUCCESS')->comment('PENDING, SUCCESS, FAILED, REFUNDED, PARTIALLY_REFUNDED')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->dropUnique('payments_gateway_payment_unique');
        });

        try {
            Schema::table('payments', function (Blueprint $t) {
                $t->index(['gateway_payment_id']);
            });
        } catch (\Throwable) {
            // Ignore if re-creation fails.
        }

        Schema::table('payments', function (Blueprint $t) {
            $t->string('status', 24)->default('COMPLETED')->comment('PENDING, COMPLETED, FAILED, REFUNDED')->change();
        });
    }
};
