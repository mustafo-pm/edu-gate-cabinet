<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return __('cabinet.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Pending => 'processing',
            self::Cancelled => 'danger',
            self::Refunded => 'refund',
        };
    }

    /**
     * Phosphor icon name — the project's icon set.
     *
     * Returned by the public API because the brand guide forbids conveying a
     * status by colour alone, and a caller on another host cannot guess which
     * glyph we mean.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Completed => 'check-circle',
            self::Pending => 'clock',
            self::Cancelled => 'x-circle',
            self::Refunded => 'arrow-counter-clockwise',
        };
    }
}
