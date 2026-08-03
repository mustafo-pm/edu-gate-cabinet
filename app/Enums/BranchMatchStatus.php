<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a branch (MFO) came to be linked to its bank.
 *
 * Money may only be routed to a branch that a human has `Confirmed` — the
 * source registry ships without bank ids, so `Auto` links are a best-effort
 * guess derived from the branch name and can be wrong.
 */
enum BranchMatchStatus: string
{
    case Unmapped = 'unmapped';   // no bank resolved — needs a human
    case Auto = 'auto';           // matched by name, NOT yet verified
    case Confirmed = 'confirmed'; // verified by a human — safe to transfer to

    public function label(): string
    {
        return match ($this) {
            self::Unmapped => 'Unmapped',
            self::Auto => 'Auto-matched',
            self::Confirmed => 'Confirmed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Auto => 'warning',
            self::Unmapped => 'danger',
        };
    }

    /** Only confirmed branches may receive an A2A transfer. */
    public function isPayable(): bool
    {
        return $this === self::Confirmed;
    }
}
