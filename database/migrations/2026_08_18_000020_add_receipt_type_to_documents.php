<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the documents.type enum to include billing document types
 * (RECEIPT, INVOICE) so the ReceiptService can persist receipt PDFs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores enums as VARCHAR + CHECK constraint; recreate the
        // column with the expanded value list. MySQL/MariaDB ALTER COLUMN
        // also supports redefining an enum in place.
        Schema::table('documents', function (Blueprint $t) {
            $t->enum('type', [
                'LAB_REPORT', 'IMAGING', 'PRESCRIPTION', 'DISCHARGE_SUMMARY',
                'CONSENT', 'REFERRAL', 'CLINICAL', 'RECEIPT', 'INVOICE', 'OTHER',
            ])->default('OTHER')->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->enum('type', [
                'LAB_REPORT', 'IMAGING', 'PRESCRIPTION', 'DISCHARGE_SUMMARY',
                'CONSENT', 'REFERRAL', 'CLINICAL', 'OTHER',
            ])->default('OTHER')->change();
        });
    }
};
