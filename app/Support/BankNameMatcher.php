<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Bank;

/**
 * Derives a branch's bank from its name.
 *
 * The MFO registry ships without bank ids, so the bank has to be inferred from
 * free text that is riddled with mixed Cyrillic/Latin characters and bad
 * transliteration (TASHKЕHT, TUROHBAHK, BAHKA — Cyrillic Н read as H).
 *
 * A match here is only ever a SUGGESTION (BranchMatchStatus::Auto). A human
 * confirms before money is routed — see BranchMatchStatus.
 */
final class BankNameMatcher
{
    /** Cyrillic characters that look like Latin ones, as they appear in this data. */
    private const CYR = ['А', 'В', 'Е', 'К', 'М', 'Н', 'О', 'Р', 'С', 'Т', 'У', 'Х', 'З', 'І', 'Ј', 'а', 'в', 'е', 'к', 'м', 'н', 'о', 'р', 'с', 'т', 'у', 'х'];

    private const LAT = ['A', 'B', 'E', 'K', 'M', 'H', 'O', 'P', 'C', 'T', 'Y', 'X', '3', 'I', 'J', 'a', 'b', 'e', 'k', 'm', 'h', 'o', 'p', 'c', 't', 'y', 'x'];

    /**
     * Extra spellings per bank slug. The registry uses old names, Russian
     * forms and transliterations that never match the modern name.
     */
    private const ALIASES = [
        'xalqbanki' => ['XALK BANKI', 'XALQ BANK', 'XALK BANK', 'NARODNIY BANK'],
        'nbu' => ['MILLIY BANK', 'NBU', 'NACIONALNIY BANK'],
        'sqb' => ['UZPSB', 'PROMSTROYBANK', 'PROMCTPOY', 'SANOAT QURILISH', 'SQB'],
        'brb' => ['QISHLOQ QURILISH', 'KISHLOK KURILISH', 'BRB'],
        'ipakyolibank' => ['IPAK YULI', 'IPAK YOLI'],
        'ofb' => ['ORIENT FINANS', 'ORIENT FINANCE'],
        'asiaalliancebank' => ['ASIA ALLIANCE', 'A3IYA ALYANC'],
        'garantbank' => ['SAVDOGAR', 'GARANT'],
        'octobank' => ['RAVNAQ', 'RAVNAK', 'OCTO'],
        'avo' => ['UZAGROEXPORT', 'AGROEXPORT', 'AVO BANK'],
        'mkbank' => ['MIKROKREDIT'],
        'hamkorbank' => ['HAMKORBANK', 'XAMKORBANK', 'HAMKOR BANK'],
        'turonbank' => ['TURONBANK', 'TUROHBAHK', 'TURON BANK'],
        'default' => ['MARKAZIY BANK', 'CENTRALN', 'XAZINACHILIK', 'MOLIYA VAZIRLIGI'],
    ];

    /**
     * Words that contain a bank name as a substring but do NOT indicate that
     * bank. Without this, every branch described as "with foreign CAPITAL
     * participation" (KAPITALI ISHTIROKIDAGI) is mis-read as Kapital Bank.
     */
    private const STOP_PHRASES = [
        'KAPITALI ISHTIROKIDAGI', 'KAPITALI ISHTIROKIDA', 'KAPITAL ISHTIROKIDAGI',
        'YCHACTIEM KAPITALA', 'INOSTRANNIM KAPITALOM',
    ];

    /** @var array<string, string[]>|null  slug => search tokens */
    private static ?array $tokens = null;

    /** Normalise: fold Cyrillic look-alikes to Latin, upper-case, strip noise. */
    public static function normalise(string $value): string
    {
        $value = str_replace(self::CYR, self::LAT, $value);
        $value = mb_strtoupper($value, 'UTF-8');

        return trim((string) preg_replace('/[^A-Z0-9]+/u', ' ', $value));
    }

    /** Build the token list once per request, longest token first. */
    private static function tokens(): array
    {
        if (self::$tokens !== null) {
            return self::$tokens;
        }

        $map = [];
        foreach (Bank::all() as $bank) {
            $tokens = self::ALIASES[$bank->slug] ?? [];

            foreach ([$bank->name_uz, $bank->name_en, $bank->name_ru] as $name) {
                if (! $name) {
                    continue;
                }
                // "Aloqa Bank" / "Aloqabank" -> "ALOQA"
                $core = trim((string) preg_replace('/\s*(BANK|BANKI|BANKA)\s*$/', '', self::normalise($name)));
                if (mb_strlen($core) >= 3) {
                    $tokens[] = $core;
                }
            }

            $tokens = array_values(array_unique(array_filter(array_map(
                fn ($t) => self::normalise($t),
                $tokens,
            ))));

            if ($tokens) {
                $map[$bank->slug] = $tokens;
            }
        }

        // Longest token first so "ASIA ALLIANCE" wins before a shorter "ASIA".
        uasort($map, fn ($a, $b) => max(array_map('mb_strlen', $b)) <=> max(array_map('mb_strlen', $a)));

        return self::$tokens = $map;
    }

    public static function flush(): void
    {
        self::$tokens = null;
    }

    /**
     * Best-guess bank slug for a branch name, or null when nothing matches.
     */
    public static function match(string $branchName): ?string
    {
        $haystack = self::normalise($branchName);

        // Remove misleading phrases before looking for bank names.
        foreach (self::STOP_PHRASES as $phrase) {
            $haystack = str_replace(self::normalise($phrase), ' ', $haystack);
        }

        foreach (self::tokens() as $slug => $tokens) {
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    return $slug;
                }
            }
        }

        return null;
    }
}
