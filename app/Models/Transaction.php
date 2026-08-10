<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Models\Concerns\ScopedToMerchant;
use App\Models\Concerns\ScopedToPsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Append-only record of a payment. Visible to the owning merchant (merchant
 * guard) and the owning PSP (psp guard); admin/API see all. Never hard-deleted.
 */
class Transaction extends Model implements AuditableContract
{
    use Auditable;
    use ScopedToMerchant;
    use ScopedToPsp;

    protected $fillable = [
        'psp_id', 'merchant_id', 'student_id', 'payment_schedule_id',
        'partner_transaction_id', 'check_id', 'idempotency_key',
        'amount', 'commission_amount', 'net_amount',
        'status', 'gateway', 'refunded_transaction_id', 'meta', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',            // tiyin
            'commission_amount' => 'integer', // tiyin
            'net_amount' => 'integer',        // tiyin
            'status' => TransactionStatus::class,
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function psp(): BelongsTo
    {
        return $this->belongsTo(Psp::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    /** The public receipt, issued on first request rather than up front. */
    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    /**
     * Outbound postings that settle this payment. Plural because a failed
     * transfer is never edited — a retry is a new append-only row, so one
     * payment can carry several attempts.
     */
    public function bankTransfers(): HasMany
    {
        return $this->hasMany(BankTransfer::class);
    }
}
