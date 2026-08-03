<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** One of EduGate's own bank accounts — the sender side of an A2A transfer. */
class SettlementAccount extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'bank_id', 'label', 'account', 'mfo', 'tax', 'holder_name',
        'driver', 'balance', 'balance_updated_at', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer', // tiyin
            'balance_updated_at' => 'datetime',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(BankTransfer::class);
    }

    /** The `sender` block of the A2A payload. */
    public function toSenderPayload(): array
    {
        return [
            'account' => $this->account,
            'code_filial' => $this->mfo,
            'tax' => $this->tax,
            'name' => $this->holder_name,
        ];
    }

    /**
     * Pick the account to send from: prefer one held at the recipient's own
     * bank (same-bank transfer), otherwise fall back to the default rail.
     */
    public static function routeFor(?Bank $recipientBank): ?self
    {
        $query = static::query()->where('is_active', true);

        if ($recipientBank) {
            $sameBank = (clone $query)->where('bank_id', $recipientBank->id)->first();
            if ($sameBank) {
                return $sameBank;
            }
        }

        return $query->where('is_default', true)->first();
    }
}
