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
            $this->line('Post this in the group, then run the command again:');
            $this->newLine();
            $this->line('    /start@edu_gate_bot');

            return self::SUCCESS;
        }

        foreach ($found as $chat) {
            $record = TelegramChat::updateOrCreate(
                ['chat_id' => $chat['chat_id']],
                ['title' => $chat['title'], 'type' => $chat['type']],
            );
            $this->info(sprintf('%s  %s  (%s)%s',
                $record->wasRecentlyCreated ? 'added  ' : 'updated',
                $chat['chat_id'],
                $chat['title'] ?? $chat['type'],
                $record->is_active ? '' : ' [inactive]',
            ));
        }

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
