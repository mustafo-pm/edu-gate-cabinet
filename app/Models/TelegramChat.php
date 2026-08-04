<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A delivery destination: a chat, optionally narrowed to one forum topic.
 */
class TelegramChat extends Model
{
    protected $fillable = [
        'chat_id', 'message_thread_id', 'topic_name',
        'title', 'type', 'is_active', 'last_sent_at', 'last_error',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_sent_at' => 'datetime'];
    }

    /**
     * Register a destination found by discovery.
     *
     * New PRIVATE chats are created inactive on purpose: discovery picks up
     * anyone who has ever messaged the bot, and payment alerts should not start
     * flowing into someone's personal DMs without a deliberate decision. An
     * existing row's is_active is never overwritten.
     */
    public static function registerDiscovered(array $data): self
    {
        $chat = static::firstOrNew([
            'chat_id' => $data['chat_id'],
            'message_thread_id' => $data['message_thread_id'] ?? null,
        ]);

        if (! $chat->exists) {
            $chat->is_active = ($data['type'] ?? null) !== 'private';
        }

        $chat->fill(array_filter([
            'title' => $data['title'] ?? null,
            'type' => $data['type'] ?? null,
            'topic_name' => $data['topic_name'] ?? null,
        ], fn ($v) => $v !== null));

        $chat->save();

        return $chat;
    }

    /** Human label, e.g. "Ops group › Payments". */
    public function label(): string
    {
        $base = $this->title ?: $this->chat_id;

        if (! filled($this->message_thread_id)) {
            return $base;
        }

        return $base.' › '.($this->topic_name ?: 'Topic #'.$this->message_thread_id);
    }
}
