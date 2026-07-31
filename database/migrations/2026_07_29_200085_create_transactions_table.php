<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every payment. APPEND-ONLY — transactions are never updated destructively
 * or deleted. Refunds/corrections are recorded as new rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psp_id')->constrained('psps')->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained('payment_schedules')->nullOnDelete();

            $table->string('partner_transaction_id');   // PSP's own txn id (idempotency)
            $table->string('check_id', 40)->nullable()->index(); // from /payments/check, TTL 15m
            $table->string('idempotency_key')->nullable();

            $table->unsignedBigInteger('amount');         // tiyin — gross amount paid
            $table->unsignedBigInteger('commission_amount')->default(0); // tiyin — EduGate commission
            $table->unsignedBigInteger('net_amount');     // tiyin — settled to merchant (amount - commission)

            $table->string('status')->default('pending'); // TransactionStatus
            $table->string('gateway')->nullable();        // payment channel / rail
            $table->foreignId('refunded_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Idempotency: a PSP's transaction id is unique within that PSP.
            $table->unique(['psp_id', 'partner_transaction_id']);
            $table->index(['merchant_id', 'status']);
            $table->index(['psp_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
