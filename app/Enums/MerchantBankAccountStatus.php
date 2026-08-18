<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an institution's bank account may receive money yet.
 *
 * An institution proposes; EduGate decides. The gap between the two is the
 * whole point: changing a bank account changes where every future settlement
 * lands, so it cannot be a field a cabinet user edits and saves.
 */
enum MerchantBankAccountStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';

    /** Retired rather than deleted — old transfers still point at it. */
    case Archived = 'archived';

    public function label(): string
    {
        return __('cabinet.bank_accounts.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
            self::Archived => 'muted',
        };
    }

    /** Only an approved account may be paid into. */
    public function canReceive(): bool
    {
        return $this === self::Active;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->name])->all();
    }
}
