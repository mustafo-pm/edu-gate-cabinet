<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** Append-only record of an outbound A2A transfer to an institution. */
class BankTransfer extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'transaction_id', 'payout_id', 'merchant_id', 'settlement_account_id', 'bank_branch_id',
        'reference', 'amount',
        'recipient_account', 'recipient_mfo', 'recipient_tax', 'recipient_name',
        'purpose_code', 'purpose_text', 'driver', 'status', 'external_id',
        'request_payload', 'response_payload', 'error',
        'sent_at', 'confirmed_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', // tiyin
            'status' => BankTransferStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    /**
     * The tuition payments this posting settles — the answer accounting needs
     * when it asks "which payment is this provodka for?".
     *
     * One transfer per payment is the normal case (instant settlement); a
     * batched payout resolves to every transaction in it. A manual correction
     * has no source and returns empty rather than null, so callers can always
     * iterate.
     *
     * @return Collection<int, Transaction>
     */
    public function sourcePayments(): Collection
    {
        if ($this->transaction_id) {
            return Transaction::withoutGlobalScopes()
                ->whereKey($this->transaction_id)
                ->with(['student', 'psp'])
                ->get();
        }

        if ($this->payout_id) {
            return Transaction::withoutGlobalScopes()
                ->whereIn('id', PayoutItem::where('payout_id', $this->payout_id)->pluck('transaction_id'))
                ->with(['student', 'psp'])
                ->orderBy('paid_at')
                ->get();
        }

        return collect();
    }

    /** Short label for the source, e.g. "TXN #185" or "Payout PO-2026-01 (12)". */
    public function sourceLabel(): string
    {
        if ($this->transaction_id) {
            return 'TXN #'.$this->transaction_id;
        }

        if ($this->payout_id) {
            $count = PayoutItem::where('payout_id', $this->payout_id)->count();

            return ($this->payout?->reference ?? 'Payout #'.$this->payout_id)." ({$count})";
        }

        return 'manual';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(SettlementAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    /** The `recipient` block of the A2A payload (snapshot, not live FK data). */
    public function toRecipientPayload(): array
    {
        return [
            'account' => $this->recipient_account,
            'code_filial' => $this->recipient_mfo,
            'tax' => $this->recipient_tax,
            'name' => $this->recipient_name,
        ];
    }
}
