<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A posting can settle EITHER one payment or a batch of them.
 *
 * The instant-settlement promise means the normal case is one transfer per
 * tuition payment (`transaction_id`). But payouts exist for batched settlement,
 * and accounting has to be able to open a posting and see every payment behind
 * it — so a posting may point at a payout instead. Exactly one of the two is
 * set; neither is required, because a manual correction has no source payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('transaction_id')
                ->constrained('payouts')->nullOnDelete();

            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });
    }
};
