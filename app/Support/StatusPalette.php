<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The brand's status colours as hex, for callers that cannot use our CSS.
 *
 * The cabinet styles statuses with Tailwind tokens; a static page on another
 * host has no access to those, so the API hands it the values instead. Text and
 * background always travel together — the brand guide requires an icon or a
 * word alongside the colour, never colour on its own, so a caller that renders
 * only a coloured dot is doing it wrong.
 *
 * Source of truth is the brand guide's status table; do not invent tokens here.
 */
final class StatusPalette
{
    /** @var array<string, array{text: string, background: string}> */
    private const SWATCHES = [
        'success' => ['text' => '#059669', 'background' => '#ECFDF5'],
        'processing' => ['text' => '#0878FF', 'background' => '#EEF4FF'],
        'warning' => ['text' => '#D97706', 'background' => '#FFFBEB'],
        'danger' => ['text' => '#DC2626', 'background' => '#FEF2F2'],
        'refund' => ['text' => '#7C3AED', 'background' => '#F5F3FF'],
    ];

    /**
     * @return array{token: string, text: string, background: string}
     */
    public static function for(string $token): array
    {
        // An unknown token falls back to neutral processing blue rather than
        // throwing: a new status should never take the receipt page down.
        $swatch = self::SWATCHES[$token] ?? self::SWATCHES['processing'];

        return ['token' => $token, ...$swatch];
    }
}
