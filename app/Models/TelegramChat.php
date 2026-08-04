<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramChat extends Model
{
    protected $fillable = ['chat_id', 'title', 'type', 'is_active', 'last_sent_at', 'last_error'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_sent_at' => 'datetime'];
    }
}
