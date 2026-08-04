<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forum-topic support.
 *
 * In a supergroup with Topics enabled, every topic is a `message_thread_id`
 * and sendMessage must carry it or the message lands in "General". A row here
 * is therefore a DESTINATION (chat + optional topic), not just a chat — which
 * is why the unique key moves from chat_id to (chat_id, message_thread_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->string('message_thread_id', 40)->nullable()->after('chat_id');
            $table->string('topic_name')->nullable()->after('message_thread_id');
        });

        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->dropUnique('telegram_chats_chat_id_unique');
            $table->unique(['chat_id', 'message_thread_id'], 'telegram_chats_chat_topic_unique');
        });

        // Lets a single alert be routed to one topic instead of every destination.
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->foreignId('telegram_chat_id')->nullable()->after('is_enabled')
                ->constrained('telegram_chats')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('telegram_chat_id');
        });

        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->dropUnique('telegram_chats_chat_topic_unique');
            $table->unique('chat_id', 'telegram_chats_chat_id_unique');
            $table->dropColumn(['message_thread_id', 'topic_name']);
        });
    }
};
