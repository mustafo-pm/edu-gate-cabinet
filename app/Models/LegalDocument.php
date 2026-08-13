<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LegalDocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A public legal document: the offer, the privacy policy, an agreement.
 *
 * The row itself carries almost nothing — the slug it is served under and
 * whether it is switched on. All the text lives in versions, because the text
 * is the thing that has to be provable after the fact.
 */
class LegalDocument extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['slug', 'type', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class)->orderByDesc('version');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The version in force right now.
     *
     * Published, and its effective date has arrived. A version published today
     * to take effect next month is deliberately not returned — that is the
     * whole point of announcing a change before it binds anyone.
     */
    public function currentVersion(): ?LegalDocumentVersion
    {
        return $this->versions()
            ->whereNotNull('published_at')
            ->where(function (Builder $q) {
                // whereDate, not a string compare: the column holds
                // "2026-08-13 00:00:00" and comparing that against "2026-08-13"
                // as text puts it in the future, so a document effective today
                // would silently never appear.
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now());
            })
            ->orderByDesc('version')
            ->first();
    }

    /** Published, but not yet in force. Shown publicly as "upcoming". */
    public function upcomingVersion(): ?LegalDocumentVersion
    {
        return $this->versions()
            ->whereNotNull('published_at')
            ->whereNotNull('effective_from')
            ->whereDate('effective_from', '>', now())
            ->orderByDesc('version')
            ->first();
    }

    public function nextVersionNumber(): int
    {
        return (int) $this->versions()->max('version') + 1;
    }
}
