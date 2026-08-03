<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Bank extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'code', 'slug', 'name_uz', 'name_ru', 'name_en', 'logo_path', 'swift',
        'a2a_supported', 'a2a_driver', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'a2a_supported' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Name in the active locale. The registry only carries uz/ru/en, so the
     * other cabinet locales fall back: uz_Cyrl + kaa → uz.
     */
    public function name(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        $value = match ($locale) {
            'ru' => $this->name_ru,
            'en' => $this->name_en,
            default => $this->name_uz,
        };

        return $value ?: ($this->name_uz ?: $this->name_en ?: $this->code);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BankBranch::class);
    }

    public function settlementAccounts(): HasMany
    {
        return $this->hasMany(SettlementAccount::class);
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    /** True when we can send from an account we hold at this bank. */
    public function canSendA2a(): bool
    {
        return $this->a2a_supported
            && $this->settlementAccounts()->where('is_active', true)->exists();
    }
}
