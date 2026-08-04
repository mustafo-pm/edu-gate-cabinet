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
