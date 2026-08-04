<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Partner;
use App\Models\Psp;

/**
 * What an auto-mode public stat counts.
 *
 * Every case is a COUNT of rows. There is deliberately no money case: turnover,
 * deposit balances and commission are commercially sensitive — publishing them
 * would let anyone derive EduGate's revenue — so the type system simply gives
 * the marketing site no way to ask for them. Add a money case here and you have
 * re-opened that hole; don't.
 *
 * Counts are still rounded down before display (see SiteStat::value()), so the
 * site never advertises an exact client count either.
 */
enum StatSource: string
{
    case Partners = 'partners';
    case Institutions = 'institutions';
    case Banks = 'banks';
    case PaymentProviders = 'payment_providers';

    public function label(): string
    {
        return match ($this) {
            self::Partners => 'Published partners',
            self::Institutions => 'Active institutions',
            self::Banks => 'Banks with A2A enabled',
            self::PaymentProviders => 'Active payment providers',
        };
    }

    /** The live count behind this stat. */
    public function count(): int
    {
        return match ($this) {
            self::Partners => Partner::where('is_published', true)->count(),
            self::Institutions => Merchant::withoutGlobalScopes()
                ->where('status', MerchantStatus::Active)->count(),
            self::Banks => Bank::where('is_active', true)
                ->where('a2a_supported', true)->count(),
            self::PaymentProviders => Psp::withoutGlobalScopes()
                ->where('status', PspStatus::Active)->count(),
        };
    }

    /** @return array<string, string> value => label, for Filament selects. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
