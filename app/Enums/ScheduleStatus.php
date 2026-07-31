<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('cabinet.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Partial => 'processing',
            self::Unpaid => 'warning',
            self::Overdue => 'danger',
            self::Cancelled => 'muted',
        };
    }
}
