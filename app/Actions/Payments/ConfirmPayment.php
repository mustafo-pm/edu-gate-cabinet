<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\LedgerType;
use App\Enums\ScheduleStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentException;
use App\Jobs\SettlePayment;
use App\Models\BankTransfer;
use App\Models\Deposit;
use App\Models\Merchant;
use App\Models\PaymentSchedule;
use App\Models\Psp;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Step 2 of a payment: finalize a checked payment atomically.
 *
 * Money-integrity guarantees:
 *   • Idempotent on (psp_id, partner_transaction_id) — safe to retry.
 *   • Deposit debit + commission + schedule application happen in ONE DB
 *     transaction; a failure rolls the whole thing back.
 *   • All amounts are integer tiyin. Records are append-only.
 */
class ConfirmPayment
{
    public function __construct(private readonly ResolveCommission $resolveCommission) {}

    public function handle(
        int $pspId,
        string $checkId,
        string $partnerTransactionId,
        int $amountTiyin,
        ?string $idempotencyKey = null,
        ?string $gateway = null,
    ): Transaction {
        // 1. Idempotency — a replay returns the original transaction unchanged.
        $existing = Transaction::withoutGlobalScopes()
            ->where('psp_id', $pspId)
            ->where('partner_transaction_id', $partnerTransactionId)
            ->first();

        if ($existing) {
            // A replay is also the chance to notice that settlement never
            // happened for the original — a queue worker that was down when it
            // was confirmed would otherwise leave the institution unpaid
            // forever, since the retry returns here and never reaches step 7.
            $this->ensureSettlement($existing);

            return $existing;
        }

        // 2. Resolve the check (issued by CheckPayment, TTL 15m).
        $check = Cache::get("payment_check:{$checkId}");
        if (! $check) {
            throw PaymentException::checkExpired();
        }

        /** @var Student $student */
        $student = Student::withoutGlobalScopes()->findOrFail($check['student_id']);
        $merchantId = (int) $check['merchant_id'];

        $psp = Psp::findOrFail($pspId);
        $merchant = Merchant::findOrFail($merchantId);

        $commission = $this->resolveCommission->handle($psp, $merchant, $amountTiyin);
        $net = $amountTiyin - $commission;

        $transaction = DB::transaction(function () use (
            $pspId, $merchantId, $student, $partnerTransactionId, $amountTiyin,
            $commission, $net, $idempotencyKey, $gateway, $checkId
        ) {
            // 3. Lock the PSP's latest ledger row and read the running balance.
            $last = Deposit::withoutGlobalScopes()
                ->where('psp_id', $pspId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $balance = (int) ($last->balance_after ?? 0);

            if ($balance < $amountTiyin) {
                throw PaymentException::insufficientDeposit();
            }

            // 4. Append the transaction (append-only).
            $txn = Transaction::withoutGlobalScopes()->create([
                'psp_id' => $pspId,
                'merchant_id' => $merchantId,
                'student_id' => $student->id,
                'partner_transaction_id' => $partnerTransactionId,
                'check_id' => $checkId,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amountTiyin,
                'commission_amount' => $commission,
                'net_amount' => $net,
                'status' => TransactionStatus::Completed,
                'gateway' => $gateway,
                'paid_at' => now(),
            ]);

            // 5. Debit the deposit ledger by the full amount (append-only row).
            Deposit::withoutGlobalScopes()->create([
                'psp_id' => $pspId,
                'type' => LedgerType::Debit,
                'amount' => $amountTiyin,
                'balance_after' => $balance - $amountTiyin,
                'transaction_id' => $txn->id,
                'reference' => $partnerTransactionId,
                'description' => 'Payment debit',
            ]);

            // 6. Apply the payment to the student's outstanding schedules (oldest first).
            $this->applyToSchedules($merchantId, $student->id, $amountTiyin);

            return $txn;
        });

        // 7. Settle onward to the institution's bank.
        //
        // Deliberately AFTER the transaction and on a queue: the payment has
        // already succeeded and the deposit is debited, so a slow or broken
        // bank must not be able to fail or roll any of that back. afterCommit
        // keeps it correct if this action is ever called inside an outer
        // transaction.
        $this->ensureSettlement($transaction);

        return $transaction;
    }

    /**
     * Queue settlement unless this payment already has a posting.
     *
     * Cheap to call twice: SettleTransaction is idempotent per transaction, and
     * the existence check keeps a replayed confirm from filling the queue.
     */
    private function ensureSettlement(Transaction $transaction): void
    {
        if (! config('settlement.auto')) {
            return;
        }

        if (BankTransfer::where('transaction_id', $transaction->id)->exists()) {
            return;
        }

        SettlePayment::dispatch($transaction->id)->afterCommit();
    }

    /** Distribute a paid amount across outstanding schedules, oldest due first. */
    private function applyToSchedules(int $merchantId, int $studentId, int $amountTiyin): void
    {
        $remaining = $amountTiyin;

        $schedules = PaymentSchedule::withoutGlobalScopes()
            ->where('merchant_id', $merchantId)
            ->where('student_id', $studentId)
            ->whereIn('status', [ScheduleStatus::Unpaid->value, ScheduleStatus::Partial->value, ScheduleStatus::Overdue->value])
            ->orderBy('due_date')
            ->lockForUpdate()
            ->get();

        foreach ($schedules as $schedule) {
            if ($remaining <= 0) {
                break;
            }

            $due = $schedule->outstanding();
            $apply = min($due, $remaining);

            $schedule->paid_amount += $apply;
            $remaining -= $apply;

            $schedule->status = $schedule->paid_amount >= $schedule->amount
                ? ScheduleStatus::Paid
                : ScheduleStatus::Partial;

            $schedule->save();
        }
    }
}
