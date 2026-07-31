<?php

declare(strict_types=1);

namespace App\Enums;

enum MerchantType: string
{
    case University = 'university';
    case School = 'school';
    case Kindergarten = 'kindergarten';

    public function label(): string
    {
        return match ($this) {
            self::University => 'University',
            self::School => 'School',
            self::Kindergarten => 'Kindergarten',
        };
    }
}
