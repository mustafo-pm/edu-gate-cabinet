<?php

declare(strict_types=1);

namespace App\Enums;

enum MerchantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Badge palette key: success|processing|warning|danger|refund|muted */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'warning',
            self::Suspended => 'warning',
            self::Terminated => 'danger',
        };
    }
}
