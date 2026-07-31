<?php

declare(strict_types=1);

namespace App\Enums;

enum ApiEnvironment: string
{
    case Sandbox = 'sandbox';
    case Live = 'live';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
