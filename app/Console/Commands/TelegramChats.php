<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelegramChat;
use App\Support\Telegram;
use Illuminate\Console\Command;

/**
 * Finds the chats the bot has been added to and registers them.
 *
 *   php artisan edugate:telegram-chats           # discover + save
 *   php artisan edugate:telegram-chats --test    # also send a test message
 *
 * Telegram only reports a chat once the bot has seen an update from it, so if
 * nothing is found, post "/start@edu_gate_bot" in the group and re-run.
 */
class TelegramChats extends Command
{
    protected $signature = 'edugate:telegram-chats {--test : Send a test message to every active chat}';

    protected $description = 'Discover Telegram chats the bot belongs to and register them for alerts';

    public function handle(): int
    {
        if (! Telegram::configured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');

            return self::FAILURE;
        }

        $found = Telegram::discoverChats();

        if (! $found) {
            $this->warn('No chats found.');
            $this->line('Telegram only reveals a chat after the bot receives an update from it.');
            $this->line('Post this INSIDE the topic you want alerts in, then re-run:');
            $this->newLine();
            $this->line('    /start@edu_gate_bot');

            return self::SUCCESS;
        }

        foreach ($found as $chat) {
            $record = TelegramChat::updateOrCreate(
                [
                    'chat_id' => $chat['chat_id'],
                    'message_thread_id' => $chat['message_thread_id'],
                ],
                array_filter([
                    'title' => $chat['title'],
                    'type' => $chat['type'],
                    'topic_name' => $chat['topic_name'],
                ], fn ($v) => $v !== null),
            );

            $this->info(sprintf('%s  chat=%s  topic=%-18s %s',
                $record->wasRecentlyCreated ? 'added  ' : 'updated',
                $chat['chat_id'],
                $chat['message_thread_id'] ?? '(General)',
                $record->label(),
            ));
        }

        $this->newLine();
        $this->line('Topics only appear after the bot sees a message in them —');
        $this->line('post "/start@edu_gate_bot" inside each topic you want to use.');

        if ($this->option('test')) {
            $sent = Telegram::broadcast(
                "✅ <b>EduGate alerts connected</b>\n\nThis chat will now receive operational alerts.",
                'test',
            );
            $this->info("Test message sent to {$sent} chat(s).");
        }

        return self::SUCCESS;
    }
}
