<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A record that somebody agreed to a specific version.
 *
 * Append-only and never updated: this exists to be produced in a dispute, and
 * a row that can be edited afterwards proves nothing. It points at a version
 * rather than a document for the same reason — "they accepted the offer" is
 * worthless without saying which text that was.
 */
class LegalAcceptance extends Model
{
    protected $fillable = [
        'legal_document_version_id', 'acceptor_type', 'acceptor_id',
        'transaction_id', 'accepted_at', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    /** A cabinet user, when there is one. A payer has no account. */
    public function acceptor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The payment that constituted acceptance.
     *
     * A parent paying tuition never signs anything and never sees our cabinet;
     * under a public offer the payment itself is the acceptance, so the
     * transaction is what we can point to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Record one acceptance. Never updates an existing row. */
    public static function record(
        LegalDocumentVersion $version,
        ?Model $acceptor = null,
        ?int $transactionId = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): self {
        return static::create([
            'legal_document_version_id' => $version->id,
            'acceptor_type' => $acceptor ? $acceptor->getMorphClass() : null,
            'acceptor_id' => $acceptor?->getKey(),
            'transaction_id' => $transactionId,
            'accepted_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);
    }
}
