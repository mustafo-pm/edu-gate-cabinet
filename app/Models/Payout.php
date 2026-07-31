<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\Concerns\ScopedToMerchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** Append-only settlement batch to a merchant's bank account. */
class Payout extends Model implements AuditableContract
{
    use Auditable;
    use ScopedToMerchant;

    protected $fillable = [
        'merchant_id', 'reference', 'amount', 'status',
        'bank_account', 'bank_name', 'processed_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', // tiyin
            'status' => PayoutStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
