<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToPsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to deliver one event to one PSP.
 *
 * Append-only, like the financial tables: a retry is a new row, never an edit
 * of the failed one. When a PSP says "we never got it", the argument is settled
 * by a list of attempts with response codes — a single mutable row that only
 * remembers the last outcome cannot do that.
 *
 * Scoped to the PSP so the partner cabinet can show this table directly without
 * a forgotten `where` leaking another provider's traffic.
 */
class WebhookDelivery extends Model
{
    use ScopedToPsp;

    protected $fillable = [
        'psp_id', 'event_id', 'event', 'transaction_id',
        'url', 'payload', 'attempt', 'status_code', 'error',
        'duration_ms', 'succeeded',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'succeeded' => 'boolean',
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
