<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables behind the Aloqabank API simulator.
 *
 * These are NOT EduGate data. They stand in for records that live inside the
 * bank, so the cabinet can send real HTTP requests to something that behaves
 * like Aloqabank while we have no access to their sandbox. Everything here is
 * prefixed `sim_` so a fake ledger is never mistaken for the real one, and the
 * routes that read them are not registered in production at all.
 *
 * Field names follow the bank's documentation (camelCase on the wire, snake
 * here), including its quirks — amounts in tiyin, Cyrillic status values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_aloqabank_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username', 60)->unique();   // HTTP Basic
            $table->string('password');                 // plain: this is a fake bank, not a vault
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sim_aloqabank_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('sim_aloqabank_partners')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('activated')->default(true);
            // WORKING_WITH_CARD makes refNumber/cardType/cardNumber mandatory.
            $table->string('type', 30)->default('CARD_IS_OPTIONAL');
            $table->string('account', 20);              // partner's 20-digit account
            $table->bigInteger('balance')->default(0);  // tiyin
            $table->timestamps();
        });

        Schema::create('sim_aloqabank_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('sim_aloqabank_partners')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('sim_aloqabank_services')->cascadeOnDelete();

            // "Дважды заказ с одинаковым order_id оплатить не удастся."
            $table->string('order_id', 120);
            $table->string('doc_id', 40);
            $table->string('kind', 16);                 // payment | paymentBudget

            $table->string('receiver_name')->nullable();
            $table->string('mfo_receiver', 5)->nullable();
            $table->string('receiver_account', 27);     // 20 by requisites, 27 for budget
            $table->string('inn_receiver', 9)->nullable();
            $table->string('purpose_code', 5)->nullable();
            $table->string('ref_number', 40)->nullable();
            $table->string('card_type', 10)->nullable();
            $table->string('card_number', 16)->nullable();
            $table->text('purpose');

            $table->bigInteger('amount');               // tiyin, excluding commission
            $table->bigInteger('commission_amount')->default(0);

            // Whether the funds actually left the service balance. An order the
            // service could not afford is still accepted (and later rejected),
            // but nothing was taken — so nothing may be given back.
            $table->boolean('debited')->default(false);

            // Введен → Проведен | Удален. Stored in Cyrillic exactly as the bank
            // returns it, so our parsing is tested against the real strings.
            $table->string('payment_status', 20)->default('Введен');

            // The bank settles asynchronously. Rather than run a worker, the
            // simulator flips the status lazily once this moment has passed.
            $table->timestamp('execute_after')->nullable();
            $table->string('settles_to', 20)->default('Проведен'); // or Удален, or stays Введен
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->unique(['partner_id', 'order_id']);
            $table->index(['partner_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_aloqabank_payments');
        Schema::dropIfExists('sim_aloqabank_services');
        Schema::dropIfExists('sim_aloqabank_partners');
    }
};
