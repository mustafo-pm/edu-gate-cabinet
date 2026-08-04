<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartnerCategory;
use App\Support\Showcase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A logo shown publicly on edu-gate.uz.
 *
 * Curated, never derived: a row exists here only because someone decided this
 * organisation may be named publicly. `is_published` is a second, deliberate
 * step on top of that — creating a partner does not put it on the website.
 */
class Partner extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'slug', 'name_uz', 'name_ru', 'name_en', 'category',
        'logo_path', 'website_url', 'source_type', 'source_id',
        'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => PartnerCategory::class,
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The public endpoint is cached; an edit must show up without waiting
        // for the TTL, or the admin screen appears broken.
        static::saved(fn () => Showcase::flush());
        static::deleted(fn () => Showcase::flush());
    }

    /** The internal record this row was pre-filled from, if any. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Name in one locale, falling back so a half-translated row still renders.
     * The website only offers uz/ru/en, so there is no Cyrillic/Karakalpak case.
     */
    public function name(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $value = match ($locale) {
            'ru' => $this->name_ru,
            'en' => $this->name_en,
            default => $this->name_uz,
        };

        return $value ?: ($this->name_uz ?: $this->name_en ?: $this->slug);
    }

    /** @return array<string, string> All three website locales at once. */
    public function names(): array
    {
        return [
            'uz' => $this->name('uz'),
            'ru' => $this->name('ru'),
            'en' => $this->name('en'),
        ];
    }

    /** Absolute URL — the website is on a different host, so relative won't do. */
    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
