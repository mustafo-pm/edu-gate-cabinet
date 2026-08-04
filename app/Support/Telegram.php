<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AlertLog;
use App\Models\TelegramChat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Telegram Bot API client.
 *
 * Alerts must never break the thing they are reporting on: every send is
 * wrapped, failures are logged to `alert_logs` and swallowed, and the caller
 * only learns success/failure from the return value.
 */
final class Telegram
{
    public static function configured(): bool
    {
        return filled(config('services.telegram.token'));
    }

    private static function endpoint(string $method): string
    {
        return rtrim((string) config('services.telegram.api'), '/')
            .'/bot'.config('services.telegram.token').'/'.$method;
    }

    /** Escape user-supplied text for Telegram's HTML parse mode. */
    public static function escape(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Send to one chat. Returns true on success. */
    public static function send(string $chatId, string $html, ?string $event = null): bool
    {
        if (! self::configured()) {
            self::log($event, $chatId, $html, false, 'TELEGRAM_BOT_TOKEN is not set');

            return false;
        }

        try {
            $response = Http::timeout((int) config('services.telegram.timeout', 8))
                ->asJson()
                ->post(self::endpoint('sendMessage'), [
                    'chat_id' => $chatId,
                    'text' => $html,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            $ok = $response->successful() && (bool) $response->json('ok');
            $error = $ok ? null : (string) ($response->json('description') ?? 'HTTP '.$response->status());

            self::log($event, $chatId, $html, $ok, $error);

            return $ok;
        } catch (\Throwable $e) {
            // Never let a notification failure bubble into a payment flow.
            self::log($event, $chatId, $html, false, $e->getMessage());
            Log::warning('Telegram send failed', ['chat' => $chatId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Broadcast to every active chat. Returns how many succeeded. */
    public static function broadcast(string $html, ?string $event = null): int
    {
        $sent = 0;

        foreach (TelegramChat::where('is_active', true)->get() as $chat) {
            $ok = self::send($chat->chat_id, $html, $event);

            $chat->forceFill($ok
                ? ['last_sent_at' => now(), 'last_error' => null]
                : ['last_error' => 'Last send failed — see alert logs'],
            )->saveQuietly();

            $sent += $ok ? 1 : 0;
        }

        return $sent;
    }

    /** Chats the bot currently knows about, discovered from getUpdates. */
    public static function discoverChats(): array
    {
        if (! self::configured()) {
            return [];
        }

        try {
            $response = Http::timeout(15)->get(self::endpoint('getUpdates'), [
                'limit' => 100,
                'allowed_updates' => json_encode(['message', 'channel_post', 'my_chat_member']),
            ]);

            $found = [];
            foreach ((array) $response->json('result', []) as $update) {
                foreach (['message', 'channel_post', 'my_chat_member', 'edited_message'] as $key) {
                    $chat = data_get($update, $key.'.chat');
                    if ($chat && isset($chat['id'])) {
                        $found[(string) $chat['id']] = [
                            'chat_id' => (string) $chat['id'],
                            'title' => $chat['title'] ?? trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')) ?: ($chat['username'] ?? null),
                            'type' => $chat['type'] ?? null,
                        ];
                    }
                }
            }

            return array_values($found);
        } catch (\Throwable $e) {
            Log::warning('Telegram getUpdates failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private static function log(?string $event, ?string $chatId, string $message, bool $ok, ?string $error): void
    {
        try {
            AlertLog::create([
                'event' => $event ?? 'manual',
                'chat_id' => $chatId,
                'message' => mb_substr($message, 0, 2000),
                'ok' => $ok,
                'error' => $error,
            ]);
        } catch (\Throwable) {
            // Logging must never be the reason a request fails.
        }
    }
}
