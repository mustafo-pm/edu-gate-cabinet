<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound A2A transfers to institutions. APPEND-ONLY, like every other money
 * table — corrections are new rows, never edits.
 *
 * Recipient details are SNAPSHOT here rather than read through the FK: a
 * merchant may later change its bank account, and an audit must show where the
 * money actually went at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('settlement_account_id')->nullable()->constrained('settlement_accounts')->nullOnDelete();
            $table->foreignId('bank_branch_id')->nullable()->constrained('bank_branches')->nullOnDelete();

            // Our own reference — also the idempotency key for the bank call.
            $table->string('reference', 60)->unique();
            $table->unsignedBigInteger('amount');        // tiyin

            // recipient snapshot (A2A payload `recipient` block)
            $table->string('recipient_account', 30);
            $table->string('recipient_mfo', 5);
            $table->string('recipient_tax', 20)->nullable();
            $table->string('recipient_name');

            $table->string('purpose_code', 20)->nullable();
            $table->string('purpose_text')->nullable();

            $table->string('driver', 40)->nullable();
            $table->string('status', 20)->default('pending'); // BankTransferStatus
            $table->string('external_id')->nullable();        // bank-side id

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};
