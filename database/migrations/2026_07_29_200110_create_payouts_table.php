<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settlement batches to merchant bank accounts. APPEND-ONLY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('amount');       // tiyin — total net settled in this batch
            $table->string('status')->default('pending'); // PayoutStatus
            $table->string('bank_account', 30)->nullable();
            $table->string('bank_name')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
