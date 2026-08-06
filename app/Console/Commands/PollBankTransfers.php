<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BankTransferStatus;
use App\Models\BankTransfer;
use App\Services\A2a\A2aDriverManager;
use Illuminate\Console\Command;

/**
 * Chases postings the bank has accepted but not yet settled.
 *
 * Aloqabank answers "Введен" — entered, not paid — and only a later query says
 * "Проведен" or "Удален". Without this, every posting would sit at `sent`
 * forever and the register would never tell accounting whether the money
 * actually arrived.
 *
 * `unknown` postings are queried too but never resent: that status means we do
 * not know whether the money left, and asking is safe where resending is not.
 */
class PollBankTransfers extends Command
{
    protected $signature = 'transfers:poll {--limit=200}';

    protected $description = 'Ask the bank what became of postings that are still in flight';

    public function handle(A2aDriverManager $drivers): int
    {
        $cutoff = now()->subHours((int) config('settlement.poll_for_hours', 24));

        $pending = BankTransfer::query()
            ->whereIn('status', [BankTransferStatus::Sent, BankTransferStatus::Unknown])
            ->where('created_at', '>=', $cutoff)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing in flight.');

            return self::SUCCESS;
        }

        $settled = $rejected = $stillOpen = 0;

        foreach ($pending as $transfer) {
            $driver = $drivers->for($transfer->driver);

            if (! $driver) {
                $stillOpen++;

                continue;
            }

            $result = $driver->status($transfer);

            // Never downgrade a terminal answer we already hold, and never let
            // a transient "unknown" overwrite a real one.
            if (! $result->isFinal()) {
                $stillOpen++;

                continue;
            }

            $transfer->forceFill([
                'status' => $result->status,
                'external_id' => $result->externalId ?? $transfer->external_id,
                'response_payload' => $result->raw,
                'error' => $result->message,
                'confirmed_at' => $result->status === BankTransferStatus::Confirmed ? now() : null,
                'failed_at' => $result->status === BankTransferStatus::Failed ? now() : null,
            ])->save();

            $result->status === BankTransferStatus::Confirmed ? $settled++ : $rejected++;
        }

        $this->info("Polled {$pending->count()}: {$settled} settled, {$rejected} rejected, {$stillOpen} still open.");

        return self::SUCCESS;
    }
}
