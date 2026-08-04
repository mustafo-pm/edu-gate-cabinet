<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    protected $fillable = ['event', 'is_enabled', 'telegram_chat_id', 'threshold', 'send_at'];

    protected function casts(): array
    {
        return [
            'event' => AlertEvent::class,
            'is_enabled' => 'boolean',
            'threshold' => 'integer', // tiyin
        ];
    }

    /** Optional single destination; null means every active destination. */
    public function telegramChat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class);
    }

    public static function for(AlertEvent $event): ?self
    {
        return static::where('event', $event->value)->first();
    }

    public static function enabled(AlertEvent $event): bool
    {
        return (bool) static::where('event', $event->value)->where('is_enabled', true)->exists();
    }
}
