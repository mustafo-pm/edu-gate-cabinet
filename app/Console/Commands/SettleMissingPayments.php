<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Jobs\SettlePayment;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Finds completed payments that never produced a posting, and queues them.
 *
 * Settlement is queued, so if no worker is running when a payment is confirmed
 * the job simply waits — and if the queue is ever flushed, it is lost silently
 * and the institution is never paid. Nothing else in the system would notice.
 * This is the backstop: it compares payments against postings and re-queues the
 * difference, so a worker outage costs a delay rather than a missing transfer.
 *
 * Safe to run repeatedly — SettleTransaction is idempotent per transaction.
 */
class SettleMissingPayments extends Command
{
    protected $signature = 'transfers:settle-missing {--limit=500} {--days=30} {--dry-run}';

    protected $description = 'Queue settlement for completed payments that have no posting';

    public function handle(): int
    {
        $startAt = config('settlement.start_at');

        $missing = Transaction::withoutGlobalScopes()
            ->where('status', TransactionStatus::Completed)
            ->where('paid_at', '>=', now()->subDays((int) $this->option('days')))
            // Never reach back before settlement went live: those payments were
            // almost certainly transferred by hand, and paying them again would
            // be far worse than paying them late.
            ->when($startAt, fn ($q) => $q->where('paid_at', '>=', $startAt))
            ->whereDoesntHave('bankTransfers')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($missing->isEmpty()) {
            $this->info('Every completed payment has a posting.');

            return self::SUCCESS;
        }

        $this->warn("{$missing->count()} completed payment(s) have no posting.");

        if ($this->option('dry-run')) {
            $this->table(
                ['Payment', 'Paid at', 'Net'],
                $missing->map(fn (Transaction $t) => [
                    '#'.$t->id, (string) $t->paid_at, $t->net_amount,
                ])->all(),
            );

            return self::SUCCESS;
        }

        foreach ($missing as $transaction) {
            SettlePayment::dispatch($transaction->id);
        }

        $this->info("Queued {$missing->count()} for settlement.");

        return self::SUCCESS;
    }
}
