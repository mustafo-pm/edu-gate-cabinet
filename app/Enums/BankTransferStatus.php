<?php

declare(strict_types=1);

namespace App\Enums;

enum BankTransferStatus: string
{
    case Pending = 'pending';     // recorded, not yet sent to the bank
    case Sent = 'sent';           // accepted by the bank, awaiting settlement
    case Confirmed = 'confirmed'; // bank confirmed the money moved
    case Failed = 'failed';       // bank rejected it — safe to retry
    case Unknown = 'unknown';     // timeout / no answer — DO NOT auto-retry

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Sent => 'info',
            self::Pending => 'warning',
            self::Failed => 'danger',
            self::Unknown => 'gray',
        };
    }

    /** Terminal states no longer change on their own. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Confirmed, self::Failed], true);
    }

    /**
     * `Unknown` means we never learned whether the money left. Retrying could
     * pay twice, so it must be reconciled by a human against the bank statement.
     */
    public function needsReview(): bool
    {
        return $this === self::Unknown;
    }
}
