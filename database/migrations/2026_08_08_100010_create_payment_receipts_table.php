<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public payment receipts — the document a payer shows their institution.
 *
 * `code` is what appears in the QR and the URL, and it is deliberately RANDOM
 * rather than the payment id. With a sequential id anyone could walk
 * /chek/1, /chek/2, /chek/3 and harvest every student's name, institution and
 * amount. That is not brute force — it is just counting. A random code makes
 * the address unguessable, which is the only thing standing between a public
 * page and the whole payment history.
 *
 * The named fields are a SNAPSHOT taken when the receipt is issued. A student
 * may be renamed or an institution rebranded later; a receipt already handed to
 * someone must keep saying what it said. Status is the one thing read live —
 * see PaymentReceipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained('transactions')->cascadeOnDelete();

            // Unguessable. Indexed because every public page view looks it up.
            $table->string('code', 64)->unique();

            // Human-readable, printed on the document: EG-2026-000186
            $table->string('number', 40)->unique();

            // Snapshot of what was true at issue time.
            $table->string('institution_name');
            $table->string('student_name')->nullable();
            $table->string('student_number', 50)->nullable();
            $table->string('psp_name')->nullable();
            $table->unsignedBigInteger('amount');          // tiyin, what the payer paid
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
