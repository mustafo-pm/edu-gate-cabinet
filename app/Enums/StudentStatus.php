<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('cabinet.status.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Active ? 'success' : 'muted';
    }
}
