<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantBankAccountStatus;
use App\Models\Concerns\ScopedToMerchant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One bank account belonging to an institution.
 *
 * Several may exist at once. A university that moves from Davr to Ipak Yuli
 * runs both for a term before retiring the old one, and settlements have to
 * keep working throughout — so accounts are added and retired rather than
 * edited, and `is_primary` says which one money is actually sent to.
 *
 * Audited, because this is the record that answers "who changed where our
 * money goes, and when".
 */
class MerchantBankAccount extends Model implements AuditableContract
{
    use Auditable;
    use ScopedToMerchant;

    protected $fillable = [
        'merchant_id', 'label', 'bank_name', 'mfo', 'account_number', 'bank_id',
        'status', 'approved_at', 'approved_by', 'rejection_reason', 'is_primary',
    ];

    /**
     * Mirrors the database defaults so a freshly created model is already
     * correct in memory. Without this `$account->status` is null until the row
     * is reloaded, and `status->canReceive()` fatals on the line after create().
     */
    protected $attributes = [
        'status' => 'pending',
        'is_primary' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => MerchantBankAccountStatus::class,
            'approved_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'approved_by');
    }

    /** The branch this account sits at, resolved from its MFO. */
    public function branch(): ?BankBranch
    {
        return BankBranch::findByMfo($this->mfo);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', MerchantBankAccountStatus::Active);
    }

    public function canReceive(): bool
    {
        return $this->status->canReceive();
    }

    /**
     * Make this the account settlements are sent to.
     *
     * Refuses anything not approved: promoting a pending account would route
     * real money to a number nobody has checked, which is exactly what the
     * approval step exists to prevent.
     */
    public function makePrimary(): void
    {
        if (! $this->canReceive()) {
            throw new \RuntimeException('Only an approved account can receive settlements.');
        }

        static::withoutGlobalScopes()
            ->where('merchant_id', $this->merchant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->forceFill(['is_primary' => true])->save();
    }

    /** Masked for display: the tail is enough to tell two accounts apart. */
    public function maskedNumber(): string
    {
        $n = (string) $this->account_number;

        return strlen($n) <= 4 ? $n : str_repeat('•', 4).' '.substr($n, -4);
    }
}
