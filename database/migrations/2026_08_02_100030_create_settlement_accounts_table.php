<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EduGate's OWN bank accounts — the `sender` side of an A2A transfer.
 *
 * Start with one (Aloqabank) and add banks over time: holding an account at the
 * recipient's own bank makes the transfer same-bank instead of interbank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->string('label');                     // e.g. "Aloqabank — main"

            // These map 1:1 onto the A2A payload's `sender` block.
            $table->string('account', 30);               // sender.account
            $table->string('mfo', 5);                    // sender.code_filial
            $table->string('tax', 20);                   // sender.tax  (our STIR)
            $table->string('holder_name');               // sender.name (our legal name)

            $table->string('driver', 40)->nullable();    // which A2A integration sends from here
            $table->bigInteger('balance')->default(0);   // tiyin — last known, mirrors the bank
            $table->timestamp('balance_updated_at')->nullable();

            $table->boolean('is_default')->default(false); // fallback rail for other banks
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_accounts');
    }
};
