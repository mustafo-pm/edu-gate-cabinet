<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Merchant extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'name', 'name_uz', 'name_ru', 'name_en', 'legal_name', 'type', 'status',
        'stir', 'mfo', 'bank_account', 'bank_name', 'bank_id', 'commission_bps',
        'contact_name', 'contact_phone', 'contact_email',
        'website_url', 'address',
        'logo_light_path', 'logo_dark_path', 'banner_path', 'show_on_receipt',
    ];

    protected function casts(): array
    {
        return [
            'type' => MerchantType::class,
            'status' => MerchantStatus::class,
            'commission_bps' => 'integer',
            'show_on_receipt' => 'boolean',
        ];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MerchantBankAccount::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MerchantContact::class)->orderBy('sort_order');
    }

    /**
     * The approved account settlements are sent to.
     *
     * Falls back to the first approved one when no primary has been marked, so
     * an institution with exactly one account never has to press a button it
     * was not told about. Returns null rather than guessing when nothing is
     * approved — SettleTransaction turns that into a held payment with a
     * readable reason.
     */
    public function primaryBankAccount(): ?MerchantBankAccount
    {
        $accounts = $this->bankAccounts()->approved();

        return (clone $accounts)->where('is_primary', true)->first()
            ?? $accounts->orderBy('id')->first();
    }

    /**
     * Display name in one locale, falling back rather than showing a blank.
     *
     * `name` stays the admin-facing label and the last resort: a half-filled
     * profile must never render an institution with no name on a receipt.
     */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $value = match ($locale) {
            'ru' => $this->name_ru,
            'en' => $this->name_en,
            default => $this->name_uz,
        };

        return $value ?: ($this->name_uz ?: $this->name);
    }

    /** @return array<string, string> all three website locales at once */
    public function displayNames(): array
    {
        return [
            'uz' => $this->displayName('uz'),
            'ru' => $this->displayName('ru'),
            'en' => $this->displayName('en'),
        ];
    }

    public function logoUrl(bool $dark = false): ?string
    {
        $path = $dark ? ($this->logo_dark_path ?: $this->logo_light_path) : $this->logo_light_path;

        return blank($path) ? null : Storage::disk('public')->url($path);
    }

    public function bannerUrl(): ?string
    {
        return blank($this->banner_path) ? null : Storage::disk('public')->url($this->banner_path);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** The branch holding this merchant's account, resolved from its MFO. */
    public function branch(): ?BankBranch
    {
        return $this->mfo ? BankBranch::findByMfo($this->mfo) : null;
    }

    /** Name to send to the bank — legal name if we have it, else display name. */
    public function payeeName(): string
    {
        return $this->legal_name ?: $this->name;
    }

    public function users(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
