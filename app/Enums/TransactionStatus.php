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
}
