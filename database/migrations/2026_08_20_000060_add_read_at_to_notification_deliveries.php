<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The in-app notifications inbox (list + mark-read) uses read_at, but the
 * base migration never created it on notification_deliveries. MySQL catches
 * the missing column; SQLite in-memory tests silently accepted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $t) {
            $t->timestamp('read_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $t) {
            $t->dropColumn('read_at');
        });
    }
};
