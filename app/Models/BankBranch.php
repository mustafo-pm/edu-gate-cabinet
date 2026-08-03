<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BranchMatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class BankBranch extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'bank_id', 'mfo', 'name_uz', 'name_ru', 'name_en', 'region',
        'match_status', 'match_note', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'match_status' => BranchMatchStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        $value = match ($locale) {
            'ru' => $this->name_ru,
            'en' => $this->name_en,
            default => $this->name_uz,
        };

        return $value ?: ($this->name_uz ?: $this->name_en ?: $this->mfo);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** Money may only be routed to a branch a human has confirmed. */
    public function isPayable(): bool
    {
        return $this->bank_id !== null && $this->match_status->isPayable();
    }

    public function scopePayable(Builder $q): Builder
    {
        return $q->whereNotNull('bank_id')
            ->where('match_status', BranchMatchStatus::Confirmed)
            ->where('is_active', true);
    }

    public function scopeNeedsReview(Builder $q): Builder
    {
        return $q->whereIn('match_status', [
            BranchMatchStatus::Unmapped->value,
            BranchMatchStatus::Auto->value,
        ]);
    }

    /** Resolve an account's MFO to its branch. */
    public static function findByMfo(string $mfo): ?self
    {
        return static::query()->where('mfo', str_pad(trim($mfo), 5, '0', STR_PAD_LEFT))->first();
    }
}
