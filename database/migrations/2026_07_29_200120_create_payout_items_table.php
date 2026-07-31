<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links transactions into a settlement batch without mutating the (append-only)
 * transactions rows. Each completed transaction settles in exactly one payout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained('payouts')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->unsignedBigInteger('net_amount');  // tiyin — the transaction's net settled here
            $table->timestamps();

            $table->unique('transaction_id'); // a transaction settles in only one payout
            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
