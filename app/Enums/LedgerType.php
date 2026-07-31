<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerType: string
{
    case Credit = 'credit'; // PSP tops up deposit
    case Debit = 'debit';   // deducted on each successful payment

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Signed multiplier applied to the running balance. */
    public function sign(): int
    {
        return $this === self::Credit ? 1 : -1;
    }
}
