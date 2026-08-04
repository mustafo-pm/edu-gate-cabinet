<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AlertEvent;
use App\Enums\LedgerType;
use App\Models\AlertRule;
use App\Models\Deposit;
use App\Support\Alerts;

/**
 * Watches the append-only deposit ledger and raises alerts.
 *
 * Both branches only *queue* work (SendAlert runs after commit), so this stays
 * safe inside ConfirmPayment's transaction.
 */
class DepositObserver
{
    public function created(Deposit $deposit): void
    {
        // A credit means the partner added funds.
        if ($deposit->type === LedgerType::Credit) {
            Alerts::raise(AlertEvent::DepositToppedUp, [
                'psp' => $deposit->psp?->name,
                'amount' => $deposit->amount,
                'balance' => $deposit->balance_after,
                'reference' => $deposit->reference,
            ]);

            return;
        }

        // A debit may have pushed the balance under the threshold.
        $rule = AlertRule::for(AlertEvent::DepositLow);
        if (! $rule || ! $rule->is_enabled || $rule->threshold === null) {
            return;
        }

        if ($deposit->balance_after < $rule->threshold) {
            Alerts::raise(AlertEvent::DepositLow, [
                'psp' => $deposit->psp?->name,
                'balance' => $deposit->balance_after,
                'threshold' => $rule->threshold,
            ]);
        }
    }
}
