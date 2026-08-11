<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound webhooks to PSPs.
 *
 * `webhook_url` already existed on `psps` but nothing ever read it. These
 * columns are what turn it into a working delivery target.
 *
 * The secret is ENCRYPTED, not hashed. An API key can be hashed because we only
 * ever verify one the caller presents; a webhook secret is the opposite — we
 * have to reproduce it on every send to sign the body, so a one-way hash would
 * make it useless. It is therefore readable by anyone who holds both the
 * database and APP_KEY, which is the same bar as the rest of our secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psps', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('webhook_url');

            // Separate from the URL so support can silence a PSP whose endpoint
            // is melting down without destroying the address they configured.
            $table->boolean('webhook_enabled')->default(false)->after('webhook_secret');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psp_id')->constrained('psps')->cascadeOnDelete();

            // Stable across retries: the PSP dedupes on this, so a redelivery
            // must never look like a second event.
            $table->uuid('event_id')->index();
            $table->string('event', 60);              // payment.completed, ...

            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->string('url');                    // as resolved at send time
            $table->json('payload');

            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('succeeded')->default(false);

            $table->timestamps();

            // "Show me this PSP's recent deliveries" and "everything about this
            // event" are the only two ways anyone reads this table.
            $table->index(['psp_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');

        Schema::table('psps', function (Blueprint $table) {
            $table->dropColumn(['webhook_secret', 'webhook_enabled']);
        });
    }
};
