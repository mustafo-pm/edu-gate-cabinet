<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\DeliverWebhook;
use App\Models\Psp;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Raising an event for a PSP.
 *
 * Only final states are announced. A `pending` payment is still moving and a
 * PSP that acted on it would have to undo the action; by the time we speak, the
 * answer will not change.
 */
final class Webhooks
{
    public const PAYMENT_COMPLETED = 'payment.completed';

    public const PAYMENT_CANCELLED = 'payment.cancelled';

    public const PAYMENT_REFUNDED = 'payment.refunded';

    /** Signature header, so a PSP can prove the call came from us. */
    public const SIGNATURE_HEADER = 'X-EduGate-Signature';

    public const TIMESTAMP_HEADER = 'X-EduGate-Timestamp';

    public const EVENT_HEADER = 'X-EduGate-Event';

    public const DELIVERY_HEADER = 'X-EduGate-Delivery';

    public static function paymentCompleted(Transaction $transaction): void
    {
        self::forTransaction(self::PAYMENT_COMPLETED, $transaction);
    }

    public static function paymentCancelled(Transaction $transaction): void
    {
        self::forTransaction(self::PAYMENT_CANCELLED, $transaction);
    }

    public static function paymentRefunded(Transaction $transaction): void
    {
        self::forTransaction(self::PAYMENT_REFUNDED, $transaction);
    }

    private static function forTransaction(string $event, Transaction $transaction): void
    {
        $psp = Psp::find($transaction->psp_id);

        if (! $psp || ! self::configured($psp)) {
            return;
        }

        self::send($psp, $event, self::transactionPayload($event, $transaction), $transaction->id);
    }

    public static function configured(?Psp $psp): bool
    {
        return $psp
            && $psp->webhook_enabled
            && filled($psp->webhook_url)
            && filled($psp->webhook_secret);
    }

    /**
     * Queue one delivery.
     *
     * afterCommit because payment events are raised from inside the money
     * transaction: a rolled-back payment must never be announced, and a webhook
     * cannot be recalled once a PSP has credited a customer on the strength of
     * it.
     */
    public static function send(Psp $psp, string $event, array $data, ?int $transactionId = null): void
    {
        DeliverWebhook::dispatch(
            pspId: $psp->id,
            event: $event,
            eventId: (string) Str::uuid(),
            data: $data,
            transactionId: $transactionId,
        )->afterCommit();
    }

    /**
     * What a PSP is told about a payment.
     *
     * Their own reference leads, because that is the key they hold; ours is
     * included so support conversations have a shared number. Amounts in tiyin,
     * like everywhere else in the API.
     */
    public static function transactionPayload(string $event, Transaction $transaction): array
    {
        $transaction->loadMissing(['merchant', 'student']);

        return [
            'partner_transaction_id' => $transaction->partner_transaction_id,
            'payment_id' => $transaction->id,
            'status' => $transaction->status->value,
            'amount' => $transaction->amount,
            'commission_amount' => $transaction->commission_amount,
            'net_amount' => $transaction->net_amount,
            'currency' => 'UZS',
            'institution' => $transaction->merchant?->name,
            'student_number' => $transaction->student?->student_id_number,
            'paid_at' => $transaction->paid_at?->toIso8601String(),
        ];
    }

    /**
     * The signature a PSP should recompute.
     *
     * The timestamp is signed alongside the body so a captured request cannot
     * be replayed later with a fresh timestamp header; the PSP rejects anything
     * more than a few minutes old.
     */
    public static function signature(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** A fresh secret. Shown to the PSP once and never again. */
    public static function freshSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }
}
