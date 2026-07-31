<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionScope: string
{
    case Global = 'global';
    case Merchant = 'merchant';
    case Psp = 'psp';
    case Category = 'category';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Resolution priority — higher wins (category > merchant > psp > global). */
    public function weight(): int
    {
        return match ($this) {
            self::Category => 40,
            self::Merchant => 30,
            self::Psp => 20,
            self::Global => 10,
        };
    }
}
