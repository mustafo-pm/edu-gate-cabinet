<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Money is stored everywhere as an INTEGER number of tiyin (1 UZS = 100 tiyin).
 * This helper only formats for display and parses user input — it never lets a
 * float touch a stored value. Do arithmetic on the integer tiyin, not on these.
 */
final class Money
{
    public const TIYIN_PER_SOM = 100;

    /** Format tiyin as "12 345.67 UZS" (thin-space grouped). */
    public static function format(int $tiyin, bool $withCurrency = true): string
    {
        $negative = $tiyin < 0;
        $tiyin = abs($tiyin);

        $som = intdiv($tiyin, self::TIYIN_PER_SOM);
        $frac = $tiyin % self::TIYIN_PER_SOM;

        $grouped = number_format($som, 0, '.', ' ');
        $out = sprintf('%s.%02d', $grouped, $frac);

        if ($negative) {
            $out = '-'.$out;
        }

        return $withCurrency ? $out.' UZS' : $out;
    }

    /** Format tiyin as plain som with no fraction/currency, e.g. "12 345". */
    public static function som(int $tiyin): string
    {
        return number_format(intdiv($tiyin, self::TIYIN_PER_SOM), 0, '.', ' ');
    }

    /** Parse a som string/number (e.g. "12345.50") into integer tiyin. */
    public static function toTiyin(int|float|string $som): int
    {
        if (is_string($som)) {
            $som = str_replace([' ', ','], ['', '.'], trim($som));
        }

        // Round to nearest tiyin to avoid float drift, then cast to int.
        return (int) round(((float) $som) * self::TIYIN_PER_SOM);
    }
}
