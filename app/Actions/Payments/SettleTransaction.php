<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BankTransferStatus;
use App\Enums\TransactionStatus;
use App\Models\BankBranch;
use App\Models\BankTransfer;
use App\Models\Merchant;
use App\Models\SettlementAccount;
use App\Models\Transaction;
use App\Services\A2a\A2aDriverManager;
use Illuminate\Support\Facades\DB;

/**
 * Turns a completed tuition payment into an outbound posting (provodka).
 *
 * The posting is ALWAYS written, even when we cannot send it. An institution
 * we owe money to but cannot pay is exactly what accounting needs to see; doing
 * nothing would hide the debt. Such a posting stays `pending` with the reason
 * in `error`, and appears in the register as blocked rather than absent.
 *
 * Money safety:
 *   • Idempotent per transaction — one posting, enforced by the unique
 *     `reference` and checked before insert.
 *   • The recipient is SNAPSHOT, not referenced: an institution may change its
 *     bank details later and the audit must show where the money actually went.
 *   • Only a Confirmed branch may receive money. The MFO registry ships without
 *     bank ids, so an auto-matched branch is a guess, and a guess must never
 *     route a payment.
 */
class SettleTransaction
{
    public function __construct(private readonly A2aDriverManager $drivers) {}

    public function handle(Transaction $transaction, bool $send = true): ?BankTransfer
    {
        if ($transaction->status !== TransactionStatus::Completed) {
            return null;
        }

        // Idempotency: a retried job must not create a second posting. Keyed on
        // the transaction rather than the reference, so a manual re-send (which
        // gets a fresh reference) still counts as "already settled".
        $existing = BankTransfer::where('transaction_id', $transaction->id)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $reference = $this->reference($transaction);

        $merchant = $transaction->merchant()->withoutGlobalScopes()->first();

        if (! $merchant) {
            return null;
        }

        // Where the institution is paid. An approved row wins; the old
        // single-account columns on `merchants` remain the fallback so a
        // profile nobody has migrated yet keeps settling.
        $payee = $this->payeeAccount($merchant);

        $branch = $payee['mfo'] ? BankBranch::where('mfo', $payee['mfo'])->first() : null;
        $account = $this->settlementAccountFor($branch?->bank_id);

        $blocker = $this->blocker($merchant, $payee, $branch, $account);

        $transfer = DB::transaction(fn () => BankTransfer::create([
            'transaction_id' => $transaction->id,
            'merchant_id' => $transaction->merchant_id,
            'settlement_account_id' => $account?->id,
            'bank_branch_id' => $branch?->id,
            'reference' => $reference,
            // What leaves for the institution is the payment minus our commission.
            'amount' => (int) $transaction->net_amount,
            // Snapshotted, not referenced: the institution may retire this
            // account next term and last term's posting must still say where
            // the money actually went.
            'recipient_account' => (string) $payee['account'],
            'recipient_mfo' => (string) $payee['mfo'],
            'recipient_tax' => $merchant->stir,
            'recipient_name' => $merchant->name,
            'purpose_code' => '00668',
            'purpose_text' => 'Tuition settlement · payment #'.$transaction->id,
            'driver' => $account?->driver,
            'status' => BankTransferStatus::Pending,
            'error' => $blocker,
        ]));

        if ($blocker || ! $send) {
            return $transfer;
        }

        return $this->dispatchToBank($transfer);
    }

    /** Push an already-recorded posting to the bank and store the outcome. */
    public function dispatchToBank(BankTransfer $transfer): BankTransfer
    {
        $driver = $this->drivers->for($transfer->driver);

        if (! $driver) {
            $transfer->forceFill(['error' => "No A2A driver [{$transfer->driver}]."])->save();

            return $transfer;
        }

        $result = $driver->send($transfer);

        $transfer->forceFill([
            'status' => $result->status,
            'external_id' => $result->externalId ?? $transfer->external_id,
            'request_payload' => $result->request ?? $transfer->request_payload,
            'response_payload' => $result->raw,
            'error' => $result->message,
            'sent_at' => now(),
            'confirmed_at' => $result->status === BankTransferStatus::Confirmed ? now() : null,
            'failed_at' => $result->status === BankTransferStatus::Failed ? now() : null,
        ])->save();

        return $transfer;
    }

    /**
     * Which of the institution's accounts this money is going to.
     *
     * Prefers an approved row from merchant_bank_accounts; falls back to the
     * original single-account columns on `merchants` so an institution nobody
     * has migrated yet still gets paid. A pending or rejected account is never
     * chosen — that approval step is the only thing standing between a stolen
     * cabinet password and a redirected term's tuition.
     *
     * @return array{account: string, mfo: string}
     */
    private function payeeAccount($merchant): array
    {
        $primary = $merchant->primaryBankAccount();

        return $primary !== null
            ? ['account' => (string) $primary->account_number, 'mfo' => (string) $primary->mfo]
            : ['account' => (string) ($merchant->bank_account ?? ''), 'mfo' => (string) ($merchant->mfo ?? '')];
    }

    /**
     * Why this posting cannot be sent, or null when it can.
     *
     * Returning a sentence rather than a boolean because it is shown verbatim
     * to whoever has to fix it.
     */
    private function blocker($merchant, array $payee, ?BankBranch $branch, ?SettlementAccount $account): ?string
    {
        if (blank($payee['account'])) {
            return $merchant->bankAccounts()->exists()
                ? 'Institution has bank accounts on file but none is approved yet.'
                : 'Institution has no bank account on file.';
        }

        if (blank($payee['mfo'])) {
            return 'Institution has no MFO on file.';
        }

        if (! $branch) {
            return "MFO {$payee['mfo']} is not in the branch registry.";
        }

        if (! $branch->match_status->isPayable()) {
            return "Branch MFO {$payee['mfo']} is {$branch->match_status->value}, not confirmed. "
                .'Confirm it in Banking → Branches before money is routed there.';
        }

        if (! $account) {
            return 'No active settlement account to send from for this bank.';
        }

        if (blank($account->driver)) {
            return "Settlement account '{$account->label}' has no A2A driver configured.";
        }

        return null;
    }

    /**
     * Prefer an account at the recipient's OWN bank — that makes the transfer
     * internal rather than interbank, which is the whole basis of the
     * instant-settlement promise. Fall back to the default rail.
     */
    private function settlementAccountFor(?int $bankId): ?SettlementAccount
    {
        $sameBank = $bankId
            ? SettlementAccount::where('bank_id', $bankId)->where('is_active', true)->first()
            : null;

        return $sameBank
            ?? SettlementAccount::where('is_active', true)->where('is_default', true)->first();
    }

    /**
     * Re-send a posting that never reached the bank, or that the bank rejected.
     *
     * Two different situations, deliberately handled differently:
     *
     *  • `pending` — the bank never saw it (blocked on a missing MFO, an
     *    unconfirmed branch, no driver). Once the blocker is fixed the same row
     *    can be updated and sent, because the order id is still unused.
     *
     *  • `failed` — the bank DID see it and refused. Its order id is now spent;
     *    reusing it would be rejected as a duplicate. So a new posting is
     *    appended with a fresh reference, which is also what "append-only,
     *    corrections are new rows" requires.
     *
     * `unknown` is refused outright. We do not know whether that money left,
     * and re-sending is exactly how an institution gets paid twice — it must be
     * reconciled against the bank statement instead.
     */
    public function retry(BankTransfer $transfer): BankTransfer
    {
        if (! in_array($transfer->status, [BankTransferStatus::Pending, BankTransferStatus::Failed], true)) {
            return $transfer;
        }

        $merchant = Merchant::withoutGlobalScopes()->find($transfer->merchant_id);

        if (! $merchant) {
            return $transfer;
        }

        // Re-resolve routing: the point of a retry is usually that someone has
        // since fixed the MFO, the account, or the branch's match status.
        $payee = $this->payeeAccount($merchant);
        $branch = $payee['mfo'] ? BankBranch::where('mfo', $payee['mfo'])->first() : null;
        $account = $this->settlementAccountFor($branch?->bank_id);
        $blocker = $this->blocker($merchant, $payee, $branch, $account);

        if ($blocker) {
            $transfer->forceFill(['error' => $blocker])->save();

            return $transfer;
        }

        $routing = [
            'settlement_account_id' => $account->id,
            'bank_branch_id' => $branch->id,
            'driver' => $account->driver,
            'recipient_account' => (string) $payee['account'],
            'recipient_mfo' => (string) $payee['mfo'],
            'recipient_tax' => $merchant->stir,
            'recipient_name' => $merchant->name,
            'error' => null,
        ];

        if ($transfer->status === BankTransferStatus::Pending) {
            $transfer->forceFill($routing)->save();

            return $this->dispatchToBank($transfer);
        }

        $replacement = BankTransfer::create($routing + [
            'transaction_id' => $transfer->transaction_id,
            'payout_id' => $transfer->payout_id,
            'merchant_id' => $transfer->merchant_id,
            'reference' => $this->nextReference($transfer),
            'amount' => $transfer->amount,
            'purpose_code' => $transfer->purpose_code,
            'purpose_text' => $transfer->purpose_text,
            'status' => BankTransferStatus::Pending,
        ]);

        return $this->dispatchToBank($replacement);
    }

    private function reference(Transaction $transaction): string
    {
        return 'EG-'.$transaction->id;
    }

    /** EG-190 → EG-190-r2 → EG-190-r3: a spent order id is never reused. */
    private function nextReference(BankTransfer $transfer): string
    {
        $attempt = BankTransfer::where('transaction_id', $transfer->transaction_id)->count() + 1;

        return 'EG-'.$transfer->transaction_id.'-r'.$attempt;
    }
}
