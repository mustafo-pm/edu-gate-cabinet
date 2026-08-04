<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a public partner logo is grouped on the marketing site.
 *
 * These are marketing groupings, not operational ones — a row here exists
 * because someone agreed to be named publicly, which is a separate fact from
 * whether they are a live merchant, PSP or bank in the platform.
 */
enum PartnerCategory: string
{
    case Bank = 'bank';
    case PaymentProvider = 'payment_provider';
    case Institution = 'institution';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::PaymentProvider => 'Payment provider',
            self::Institution => 'Institution',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bank => 'info',
            self::PaymentProvider => 'success',
            self::Institution => 'warning',
        };
    }

    /** Heading shown above this group on the website, per locale. */
    public function heading(): array
    {
        return match ($this) {
            self::Bank => [
                'uz' => 'Banklar',
                'ru' => 'Банки',
                'en' => 'Banks',
            ],
            self::PaymentProvider => [
                'uz' => "To'lov tashkilotlari",
                'ru' => 'Платёжные организации',
                'en' => 'Payment providers',
            ],
            self::Institution => [
                'uz' => "Ta'lim muassasalari",
                'ru' => 'Образовательные учреждения',
                'en' => 'Educational institutions',
            ],
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
