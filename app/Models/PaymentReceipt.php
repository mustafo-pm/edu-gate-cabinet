<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The receipt a payer shows their institution.
 *
 * Two halves, deliberately different:
 *
 *  • The printed details are a SNAPSHOT — frozen at issue time so a document
 *    already in someone's hands never changes meaning.
 *  • The STATUS is read live from the payment on every view. That is the whole
 *    point of the QR: paper can say "paid" long after a refund, the page
 *    cannot.
 */
class PaymentReceipt extends Model
{
    protected $fillable = [
        'transaction_id', 'code', 'number',
        'institution_name', 'student_name', 'student_number', 'psp_name',
        'amount', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get or issue the receipt for a payment.
     *
     * Lazy on purpose: payments made before this feature existed still need
     * receipts, and issuing on first request covers them without a backfill.
     * The number is derived from the payment id rather than a counter, so it is
     * stable no matter what order receipts are first opened in.
     */
    public static function forTransaction(Transaction $transaction): self
    {
        $existing = static::where('transaction_id', $transaction->id)->first();

        if ($existing) {
            return $existing;
        }

        $transaction->loadMissing(['merchant', 'student', 'psp']);

        return static::create([
            'transaction_id' => $transaction->id,
            'code' => static::freshCode(),
            'number' => sprintf('EG-%s-%06d', $transaction->paid_at?->format('Y') ?? date('Y'), $transaction->id),
            'institution_name' => $transaction->merchant?->name ?? '—',
            'student_name' => $transaction->student?->fullName(),
            'student_number' => $transaction->student?->student_id_number,
            'psp_name' => $transaction->psp?->name,
            'amount' => (int) $transaction->amount,
            'paid_at' => $transaction->paid_at,
        ]);
    }

    /**
     * 32 characters from a 32-symbol alphabet ≈ 160 bits. Guessing one is not
     * a realistic attack, which is what lets this page be public at all.
     *
     * Alphabet excludes look-alikes (0/O, 1/l/I) so a code read from paper or
     * dictated over the phone does not turn into a support call.
     */
    public static function freshCode(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $last = strlen($alphabet) - 1;

        do {
            $code = '';

            for ($i = 0; $i < 32; $i++) {
                // random_int, not rand: this is the only thing keeping the
                // page private, so it has to be cryptographically random.
                $code .= $alphabet[random_int(0, $last)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Live status, never the snapshot. */
    public function status(): TransactionStatus
    {
        return $this->transaction?->status ?? TransactionStatus::Cancelled;
    }

    /** Whether the payment still stands. */
    public function isValid(): bool
    {
        return $this->status() === TransactionStatus::Completed;
    }

    /**
     * Public URL carried by the QR code.
     *
     * Built from config rather than route(), because /chek is registered on two
     * hosts under the same route name and route() would silently return
     * whichever was registered last. The address printed on a document should
     * not depend on registration order.
     */
    public function url(): string
    {
        $base = rtrim((string) config('receipt.base_url'), '/');

        return $base.'/chek/'.$this->code;
    }

    public function pdfUrl(): string
    {
        return $this->url().'/pdf';
    }

    /**
     * The paying provider's logo, if it may be shown publicly.
     *
     * Read from the curated partner wall rather than from the PSP record, and
     * only when published. Being a PSP is a commercial fact; having your mark
     * printed on a document a stranger will hold is a decision somebody has to
     * make, and that decision already lives in `partners`. No published row
     * means no logo, and the page falls back to the name.
     */
    public function pspLogoUrl(): ?string
    {
        $pspId = $this->transaction?->psp_id;

        if (! $pspId) {
            return null;
        }

        return Partner::published()
            ->where('source_type', Psp::class)
            ->where('source_id', $pspId)
            ->first()
            ?->logoUrl();
    }
}
