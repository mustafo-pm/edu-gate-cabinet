<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of PSP prepaid balance movements.
 * Rows are NEVER updated or deleted — corrections are new rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psp_id')->constrained('psps')->cascadeOnDelete();
            $table->string('type');                    // credit|debit  (LedgerType)
            $table->unsignedBigInteger('amount');       // tiyin, always positive
            $table->bigInteger('balance_after');        // tiyin, running balance snapshot
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['psp_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
