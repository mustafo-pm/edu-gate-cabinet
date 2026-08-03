<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Merchant extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'name', 'legal_name', 'type', 'status', 'stir', 'mfo', 'bank_account', 'bank_name',
        'bank_id', 'commission_bps', 'contact_name', 'contact_phone', 'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'type' => MerchantType::class,
            'status' => MerchantStatus::class,
            'commission_bps' => 'integer',
        ];
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
