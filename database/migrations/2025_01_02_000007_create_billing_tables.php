<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('ipd_admission_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('invoice_number', 32)->unique()->index();
            $t->string('status', 24)->default('DRAFT')->index()->comment('DRAFT, ISSUED, PARTIALLY_PAID, PAID, VOID, REFUNDED');
            $t->enum('source', ['OPD', 'ONLINE_CONSULTATION', 'FOLLOW_UP', 'TREATMENT', 'PACKAGE', 'IPD', 'OTHER'])->default('OPD');
            $t->unsignedInteger('subtotal_cents')->default(0);
            $t->unsignedInteger('discount_cents')->default(0);
            $t->unsignedInteger('tax_cents')->default(0);
            $t->unsignedInteger('total_cents')->default(0);
            $t->unsignedInteger('amount_paid_cents')->default(0);
            $t->unsignedInteger('amount_due_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('payment_mode', 24)->nullable()->comment('CASH, UPI, CARD, BANK_TRANSFER, CHEQUE, OTHER, CASHFREE');
            $t->text('notes')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'patient_id']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->enum('type', ['CONSULTATION', 'TREATMENT', 'PACKAGE_SESSION', 'IPD_ROOM', 'INVESTIGATION', 'PROCEDURE', 'OTHER'])->default('OTHER');
            $t->morphs('reference');
            $t->unsignedInteger('quantity')->default(1);
            $t->unsignedInteger('unit_price_cents')->default(0);
            $t->unsignedInteger('discount_cents')->default(0);
            $t->unsignedInteger('total_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $t->string('payment_number', 32)->unique()->index();
            $t->string('gateway', 24)->default('MANUAL')->comment('MANUAL, CASHFREE');
            $t->string('gateway_payment_id')->nullable()->index();
            $t->string('gateway_order_id')->nullable()->index();
            $t->enum('method', ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE', 'OTHER', 'CASHFREE'])->default('CASH');
            $t->unsignedInteger('amount_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('status', 24)->default('SUCCESS')->index()->comment('PENDING, SUCCESS, FAILED, REFUNDED, PARTIALLY_REFUNDED');
            $t->string('cheque_number')->nullable();
            $t->string('bank_name')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('cash_register_id')->nullable();
            $t->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'invoice_id']);
            // A gateway payment must be recorded at most once — hard backstop
            // for the service-level idempotency dedup. NULL gateway_payment_id
            // (manual payments) is exempt on both SQLite and MySQL.
            $t->unique(['gateway', 'gateway_payment_id'], 'payments_gateway_payment_unique');
        });

        Schema::create('payment_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('internal_order_id', 64)->unique();
            $t->string('gateway', 24)->default('CASHFREE');
            $t->string('gateway_order_id')->nullable()->unique()->index();
            $t->morphs('payable');
            $t->unsignedInteger('amount_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('customer_email')->nullable();
            $t->string('customer_phone', 20)->nullable();
            $t->string('status', 24)->default('CREATED')->index()->comment('CREATED, PAID, FAILED, EXPIRED');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('payment_webhooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->string('gateway', 24)->index();
            $t->string('event_id')->index()->comment('idempotency key');
            $t->string('event_type')->nullable();
            $t->string('gateway_order_id')->nullable()->index();
            $t->string('gateway_payment_id')->nullable();
            $t->json('payload')->nullable();
            $t->boolean('processed')->default(false)->index();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['gateway', 'event_id'], 'webhook_event_unique');
        });

        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('refund_number', 32)->unique()->index();
            $t->string('gateway_refund_id')->nullable();
            $t->unsignedInteger('amount_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('status', 24)->default('PENDING')->index()->comment('PENDING, COMPLETED, FAILED');
            $t->string('reason')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('refunded_at')->nullable();
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('category', 48)->nullable();
            $t->string('description');
            $t->unsignedInteger('amount_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('payment_method', 24)->nullable();
            $t->foreignId('cash_register_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('expense_date')->index();
            $t->string('receipt_path')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'expense_date']);
        });

        Schema::create('cash_registers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('assigned user');
            $t->string('status', 24)->default('CLOSED')->index()->comment('OPEN, CLOSED');
            $t->unsignedInteger('opening_balance_cents')->default(0);
            $t->unsignedInteger('closing_balance_cents')->default(0);
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('cash_register_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $t->morphs('reference');
            $t->enum('type', ['CREDIT', 'DEBIT'])->default('CREDIT');
            $t->string('method', 24)->default('CASH');
            $t->unsignedInteger('amount_cents')->default(0);
            $t->string('currency', 8)->default('INR');
            $t->string('description')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'cash_register_id']);
        });

        // treatment_bookings.invoice_id FK is deferred from the treatments
        // migration (000005) because invoices doesn't exist there yet.
        Schema::table('treatment_bookings', function (Blueprint $t) {
            $t->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        // payments/expenses cash_register_id FKs are deferred because
        // cash_registers is created later in this same migration — MySQL
        // enforces referenced-table existence at constraint time.
        Schema::table('payments', function (Blueprint $t) {
            $t->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $t) {
            $t->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_entries');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
