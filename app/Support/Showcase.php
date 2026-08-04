<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PartnerCategory;
use App\Models\Partner;
use App\Models\SiteStat;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the payload behind GET /api/public/site — the curated logo wall and
 * trust figures rendered on edu-gate.uz.
 *
 * Everything here is public and unauthenticated, so the rule is simple: only
 * fields that an admin deliberately marked publishable ever leave this class.
 * No money, no counts of transactions, no tenant data.
 */
class Showcase
{
    public const CACHE_KEY = 'showcase.site';

    /** Marketing content changes rarely; the site should not hit the DB per view. */
    public const TTL_MINUTES = 10;

    public static function payload(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::TTL_MINUTES),
            fn () => self::build(),
        );
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): array
    {
        $partners = Partner::published()
            ->orderBy('sort_order')
            ->orderBy('name_uz')
            ->get();

        $groups = [];

        foreach (PartnerCategory::cases() as $category) {
            $rows = $partners->where('category', $category);

            if ($rows->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key' => $category->value,
                'heading' => $category->heading(),
                'partners' => $rows->map(fn (Partner $p) => [
                    'slug' => $p->slug,
                    'name' => $p->names(),
                    'logo' => $p->logoUrl(),
                    'url' => $p->website_url,
                ])->values()->all(),
            ];
        }

        // A stat whose value() is null is not "zero" — it is deliberately not
        // ready to be shown, so it is dropped rather than rendered empty.
        $stats = SiteStat::published()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SiteStat $s) => ['stat' => $s, 'value' => $s->value()])
            ->filter(fn (array $row) => filled($row['value']))
            ->map(fn (array $row) => [
                'key' => $row['stat']->key,
                'label' => $row['stat']->labels(),
                'value' => $row['value'],
            ])
            ->values()
            ->all();

        return ['groups' => $groups, 'stats' => $stats];
    }
}
