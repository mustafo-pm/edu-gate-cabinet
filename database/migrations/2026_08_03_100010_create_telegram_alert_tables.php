<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where alerts are delivered. Usually one internal team group.
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 40)->unique();   // may be negative for groups
            $table->string('title')->nullable();
            $table->string('type', 20)->nullable();    // group | supergroup | channel | private
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // One row per alert type, so the team can retune without a deploy.
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40)->unique();     // AlertEvent
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('threshold')->nullable(); // tiyin
            $table->string('send_at', 5)->nullable();  // "09:00" for the daily summary
            $table->timestamps();
        });

        // Audit trail — what was sent, and what failed.
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->string('chat_id', 40)->nullable();
            $table->text('message')->nullable();
            $table->boolean('ok')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
        });

        // Sensible defaults so the feature works the moment a chat is added.
        // Thresholds are in tiyin.
        \Illuminate\Support\Facades\DB::table('alert_rules')->insert([
            ['event' => 'deposit_low',       'is_enabled' => true,  'threshold' => 500000000, 'send_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['event' => 'deposit_topped_up', 'is_enabled' => true,  'threshold' => null,      'send_at' => null, 'created_at' => now(), 'updated_at' => now()],
            // Off by default: on a busy day this fires on every payment.
            ['event' => 'payment_received',  'is_enabled' => false, 'threshold' => 0,         'send_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['event' => 'daily_summary',     'is_enabled' => true,  'threshold' => null,      'send_at' => '09:00', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('telegram_chats');
    }
};
