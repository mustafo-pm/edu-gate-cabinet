<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatSource;
use App\Support\Showcase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One figure in the trust strip on edu-gate.uz.
 *
 * Two modes:
 *  - manual — a fixed display string ("0–30s", "99.9%")
 *  - auto   — a live COUNT from StatSource, rounded down before display
 *
 * No mode can produce a money figure: StatSource has no money case, and
 * manual_value is a free string an admin writes deliberately. Auto counts are
 * rounded so the site never publishes an exact client count either — "150+"
 * is a claim, "153" is a disclosure.
 */
class SiteStat extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'key', 'label_uz', 'label_ru', 'label_en',
        'mode', 'source', 'manual_value', 'round_to',
        'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'source' => StatSource::class,
            'is_published' => 'boolean',
            'round_to' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Showcase::flush());
        static::deleted(fn () => Showcase::flush());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * The string to render, or null when this stat should stay hidden.
     *
     * An auto stat hides itself while the real count is still below one
     * rounding step — "0+ institutions" on the homepage is worse than showing
     * nothing at all.
     */
    public function value(): ?string
    {
        if ($this->mode !== 'auto') {
            return filled($this->manual_value) ? $this->manual_value : null;
        }

        if (! $this->source instanceof StatSource) {
            return null;
        }

        $rounded = self::roundDown($this->source->count(), $this->round_to);

        return $rounded === null ? null : $rounded.'+';
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return [
            'uz' => $this->label_uz,
            'ru' => $this->label_ru ?: $this->label_uz,
            'en' => $this->label_en ?: $this->label_uz,
        ];
    }

    /** Floor to a step; null when it would floor to zero. */
    public static function roundDown(int $count, int $step): ?int
    {
        $step = max(1, $step);
        $floor = intdiv($count, $step) * $step;

        return $floor >= $step ? $floor : null;
    }
}
