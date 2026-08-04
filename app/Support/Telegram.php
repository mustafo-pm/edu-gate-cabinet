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

    /**
     * Send to one destination. Returns true on success.
     *
     * `$threadId` is a forum topic: without it the message lands in the
     * group's "General" topic rather than the one that was chosen.
     */
    public static function send(string $chatId, string $html, ?string $event = null, ?string $threadId = null): bool
    {
        if (! self::configured()) {
            self::log($event, $chatId, $html, false, 'TELEGRAM_BOT_TOKEN is not set');

            return false;
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if (filled($threadId)) {
                $payload['message_thread_id'] = (int) $threadId;
            }

            $response = Http::timeout((int) config('services.telegram.timeout', 8))
                ->asJson()
                ->post(self::endpoint('sendMessage'), $payload);

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

    /**
     * Deliver to every active destination, or to one specific destination when
     * an alert rule targets a single topic.
     *
     * @return int how many sends succeeded
     */
    public static function broadcast(string $html, ?string $event = null, ?TelegramChat $only = null): int
    {
        $sent = 0;

        $targets = $only
            ? collect([$only])
            : TelegramChat::where('is_active', true)->get();

        foreach ($targets as $chat) {
            $ok = self::send($chat->chat_id, $html, $event, $chat->message_thread_id);

            $chat->forceFill($ok
                ? ['last_sent_at' => now(), 'last_error' => null]
                : ['last_error' => 'Last send failed — see alert logs'],
            )->saveQuietly();

            $sent += $ok ? 1 : 0;
        }

        return $sent;
    }

    /**
     * Destinations the bot currently knows about, read from getUpdates.
     *
     * Telegram has no "list topics" endpoint — a topic only becomes visible
     * once the bot sees an update from it. So each distinct
     * (chat_id, message_thread_id) pair found in recent updates is returned as
     * its own destination, and the topic's name is recovered from the
     * `forum_topic_created` service message when Telegram includes it.
     *
     * @return array<int, array{chat_id:string, message_thread_id:?string, topic_name:?string, title:?string, type:?string}>
     */
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
                    $node = data_get($update, $key);
                    $chat = data_get($node, 'chat');

                    if (! $chat || ! isset($chat['id'])) {
                        continue;
                    }

                    // Only real forum messages carry a usable thread id.
                    $threadId = data_get($node, 'is_topic_message') ? data_get($node, 'message_thread_id') : null;

                    $topicName = data_get($node, 'forum_topic_created.name')
                        ?? data_get($node, 'reply_to_message.forum_topic_created.name')
                        ?? data_get($node, 'forum_topic_edited.name');

                    $key = $chat['id'].':'.($threadId ?? '');

                    // Keep the richest record: a later update may reveal the name.
                    $found[$key] = [
                        'chat_id' => (string) $chat['id'],
                        'message_thread_id' => $threadId !== null ? (string) $threadId : null,
                        'topic_name' => $topicName ?: ($found[$key]['topic_name'] ?? null),
                        'title' => $chat['title']
                            ?? (trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')) ?: ($chat['username'] ?? null)),
                        'type' => $chat['type'] ?? null,
                    ];
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
