<?php

declare(strict_types=1);

namespace App\Enums;

enum PspStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending, self::Suspended => 'warning',
            self::Terminated => 'danger',
        };
    }
}
