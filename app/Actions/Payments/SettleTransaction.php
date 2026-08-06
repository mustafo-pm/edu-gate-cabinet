<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BankTransferStatus;
use App\Enums\TransactionStatus;
use App\Models\BankBranch;
use App\Models\BankTransfer;
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

        $reference = $this->reference($transaction);

        // Idempotency: a retried job must not create a second posting.
        $existing = BankTransfer::where('reference', $reference)->first();

        if ($existing) {
            return $existing;
        }

        $merchant = $transaction->merchant()->withoutGlobalScopes()->first();

        if (! $merchant) {
            return null;
        }

        $branch = $merchant->mfo ? BankBranch::where('mfo', $merchant->mfo)->first() : null;
        $account = $this->settlementAccountFor($branch?->bank_id);

        $blocker = $this->blocker($merchant, $branch, $account);

        $transfer = DB::transaction(fn () => BankTransfer::create([
            'transaction_id' => $transaction->id,
            'merchant_id' => $transaction->merchant_id,
            'settlement_account_id' => $account?->id,
            'bank_branch_id' => $branch?->id,
            'reference' => $reference,
            // What leaves for the institution is the payment minus our commission.
            'amount' => (int) $transaction->net_amount,
            'recipient_account' => (string) ($merchant->bank_account ?? ''),
            'recipient_mfo' => (string) ($merchant->mfo ?? ''),
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
     * Why this posting cannot be sent, or null when it can.
     *
     * Returning a sentence rather than a boolean because it is shown verbatim
     * to whoever has to fix it.
     */
    private function blocker($merchant, ?BankBranch $branch, ?SettlementAccount $account): ?string
    {
        if (blank($merchant->bank_account)) {
            return 'Institution has no bank account on file.';
        }

        if (blank($merchant->mfo)) {
            return 'Institution has no MFO on file.';
        }

        if (! $branch) {
            return "MFO {$merchant->mfo} is not in the branch registry.";
        }

        if (! $branch->match_status->isPayable()) {
            return "Branch MFO {$merchant->mfo} is {$branch->match_status->value}, not confirmed. "
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

    private function reference(Transaction $transaction): string
    {
        return 'EG-'.$transaction->id;
    }
}
