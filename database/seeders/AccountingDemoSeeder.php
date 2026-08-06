<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BankTransferStatus;
use App\Enums\BranchMatchStatus;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\BankTransfer;
use App\Models\Merchant;
use App\Models\SettlementAccount;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

/**
 * Gives the Accounting screens something real to show in dev.
 *
 * Postings are derived from ACTUAL seeded transactions rather than invented, so
 * the traceability the screens promise is genuine: open a provodka and the
 * payment behind it is a row that exists, with the right student, PSP and
 * amount. The net amount is used, because what leaves for the institution is
 * the payment minus our commission.
 *
 * Statuses are spread across confirmed / sent / failed / unknown so the filters
 * and the "needs reconciliation" badge have something to bite on.
 */
class AccountingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $bank = Bank::where('slug', 'aloqabank')->first()
            ?? Bank::orderBy('id')->first();

        if (! $bank) {
            $this->command?->warn('No banks — run the bank importer first. Skipping.');

            return;
        }

        // Aloqabank is our first rail, driven by the simulator until we have
        // sandbox credentials.
        $bank->forceFill(['a2a_supported' => true, 'a2a_driver' => 'aloqabank'])->save();

        $account = SettlementAccount::firstOrCreate(
            ['bank_id' => $bank->id, 'account' => '20208000405273320010'],
            [
                'label' => $bank->name_uz.' — main',
                'mfo' => '00401',
                'tax' => '123456789',
                'holder_name' => 'EduGate LLC',
                'driver' => 'aloqabank',
                'balance' => 500_000_000_000,
                'balance_updated_at' => now(),
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $merchant = Merchant::withoutGlobalScopes()->first();

        if (! $merchant) {
            $this->command?->warn('No merchants — run the main seeder first. Skipping postings.');

            return;
        }

        // The branch must be the one the recipient MFO actually belongs to —
        // showing a bank that does not match the MFO on the posting is exactly
        // the plausible-but-wrong data that misleads a reconciliation.
        $branch = BankBranch::where('mfo', $merchant->mfo)->first();

        if (! $branch) {
            // No branch for the institution's MFO: fall back to one of ours and
            // use ITS mfo, so the posting stays internally consistent.
            $branch = BankBranch::where('bank_id', $bank->id)
                ->where('match_status', BranchMatchStatus::Confirmed)
                ->first()
                ?? BankBranch::where('bank_id', $bank->id)->first();
        }

        $recipientMfo = $branch?->mfo ?? ($merchant->mfo ?: '00401');

        $transactions = Transaction::withoutGlobalScopes()
            ->whereDoesntHave('bankTransfers')
            ->latest('id')
            ->limit(24)
            ->get();

        if ($transactions->isEmpty()) {
            $this->command?->info('Every transaction already has a posting. Nothing to add.');

            return;
        }

        // A deliberate spread, so the register is not uniformly green.
        $statuses = [
            BankTransferStatus::Confirmed, BankTransferStatus::Confirmed,
            BankTransferStatus::Confirmed, BankTransferStatus::Confirmed,
            BankTransferStatus::Sent, BankTransferStatus::Failed,
            BankTransferStatus::Confirmed, BankTransferStatus::Unknown,
        ];

        $created = 0;

        foreach ($transactions as $i => $txn) {
            $status = $statuses[$i % count($statuses)];
            $sentAt = $txn->paid_at?->copy()->addSeconds(12) ?? now();

            BankTransfer::create([
                'transaction_id' => $txn->id,
                'merchant_id' => $txn->merchant_id,
                'settlement_account_id' => $account->id,
                'bank_branch_id' => $branch?->id,
                'reference' => 'EG-'.$txn->id.'-'.substr(md5((string) $txn->id), 0, 6),
                'amount' => (int) $txn->net_amount,
                'recipient_account' => $merchant->bank_account ?: '29801000990248844444',
                'recipient_mfo' => $recipientMfo,
                'recipient_tax' => $merchant->stir,
                'recipient_name' => $merchant->name,
                'purpose_code' => '00668',
                'purpose_text' => 'Tuition settlement · payment #'.$txn->id,
                'driver' => 'aloqabank',
                'status' => $status,
                'external_id' => $status === BankTransferStatus::Pending ? null : '1180_'.random_int(1_000_000_000, 9_999_999_999),
                'request_payload' => [
                    'orderId' => 'EG-'.$txn->id,
                    'amount' => (string) $txn->net_amount,
                    'comissionAmount' => '0',
                    'serviceId' => '33',
                    'receiverName' => $merchant->name,
                    'mfoReceiver' => $recipientMfo,
                    'receiverAccount' => $merchant->bank_account ?: '29801000990248844444',
                ],
                'response_payload' => match ($status) {
                    BankTransferStatus::Confirmed => ['status' => 'success', 'code' => 0, 'payment_status' => 'Проведен'],
                    BankTransferStatus::Sent => ['status' => 'success', 'code' => 0, 'payment_status' => 'Введен'],
                    BankTransferStatus::Failed => ['status' => 'error', 'code' => 1013, 'message' => 'Счёт не найден'],
                    default => null,
                },
                'error' => $status === BankTransferStatus::Failed ? '1013 Счёт не найден' : null,
                'sent_at' => $sentAt,
                'confirmed_at' => $status === BankTransferStatus::Confirmed ? $sentAt->copy()->addSeconds(18) : null,
                'failed_at' => $status === BankTransferStatus::Failed ? $sentAt->copy()->addSeconds(6) : null,
            ]);

            $created++;
        }

        $this->command?->info("Created {$created} postings from real transactions.");
    }
}
