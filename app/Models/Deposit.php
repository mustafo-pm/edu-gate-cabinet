<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerType;
use App\Models\Concerns\ScopedToPsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Append-only ledger row for a PSP's prepaid balance. Never updated or deleted.
 */
class Deposit extends Model implements AuditableContract
{
    use Auditable;
    use ScopedToPsp;

    protected $fillable = [
        'psp_id', 'type', 'amount', 'balance_after',
        'transaction_id', 'reference', 'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'amount' => 'integer',        // tiyin
            'balance_after' => 'integer', // tiyin
        ];
    }

    public function psp(): BelongsTo
    {
        return $this->belongsTo(Psp::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
