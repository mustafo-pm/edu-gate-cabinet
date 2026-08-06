<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payments\SettleTransaction;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Settles one completed payment out to the institution's bank.
 *
 * Queued, and dispatched `afterCommit`, for one reason: a bank outage must
 * never fail or roll back a payment that has already succeeded. The student has
 * paid and the PSP deposit is debited — that is settled fact. Getting the money
 * onward is a separate concern that can be retried.
 *
 * Failures are swallowed and logged rather than rethrown: the posting row
 * records what happened, and the accounting register is where a human sees it.
 */
class SettlePayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $transactionId) {}

    public function handle(SettleTransaction $action): void
    {
        $transaction = Transaction::withoutGlobalScopes()->find($this->transactionId);

        if (! $transaction) {
            return;
        }

        try {
            $action->handle($transaction);
        } catch (\Throwable $e) {
            Log::error('Settlement failed for transaction '.$this->transactionId, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
