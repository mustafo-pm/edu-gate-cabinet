<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AlertEvent;
use App\Enums\TransactionStatus;
use App\Models\AlertRule;
use App\Models\Transaction;
use App\Support\Alerts;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::Completed) {
            return;
        }

        $rule = AlertRule::for(AlertEvent::PaymentReceived);
        if (! $rule || ! $rule->is_enabled) {
            return;
        }

        // The threshold keeps a busy channel quiet: only announce payments at
        // or above it (0 / null announces everything).
        if ($rule->threshold !== null && $transaction->amount < $rule->threshold) {
            return;
        }

        Alerts::raise(AlertEvent::PaymentReceived, [
            'merchant' => $transaction->merchant?->name,
            'student' => $transaction->student?->fullName(),
            'psp' => $transaction->psp?->name,
            'amount' => $transaction->amount,
            'commission' => $transaction->commission_amount,
            'reference' => $transaction->partner_transaction_id,
        ]);
    }
}
