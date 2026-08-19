<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic row-locked counters for human-readable sequential numbers
 * (invoice/payment/refund/IPD numbers, patient UID fallback). One row per
 * scope is locked with SELECT ... FOR UPDATE so concurrent generators can
 * never read the same "current max" — unlike locking an empty LIKE range
 * over the target table, which serializes on nothing when no rows exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
    }
};
