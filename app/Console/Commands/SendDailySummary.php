<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AlertEvent;
use App\Enums\TransactionStatus;
use App\Models\AlertRule;
use App\Models\Transaction;
use App\Support\Alerts;
use App\Support\Telegram;
use Illuminate\Console\Command;

/**
 * Yesterday's totals, pushed to Telegram.
 *
 *   php artisan edugate:daily-summary            # previous day
 *   php artisan edugate:daily-summary --date=2026-08-01
 *   php artisan edugate:daily-summary --force    # ignore the enabled flag
 */
class SendDailySummary extends Command
{
    protected $signature = 'edugate:daily-summary {--date= : Y-m-d, defaults to yesterday} {--force}';

    protected $description = 'Send the daily payment summary to Telegram';

    public function handle(): int
    {
        $rule = AlertRule::for(AlertEvent::DailySummary);

        if (! $this->option('force')) {
            if (! $rule || ! $rule->is_enabled) {
                $this->info('Daily summary is disabled in the admin panel — nothing sent.');

                return self::SUCCESS;
            }

            // The scheduler runs this hourly; only the configured hour sends.
            // Keeps the send time editable in the admin panel without a deploy.
            if (! $this->option('date')) {
                $configured = (int) substr($rule->send_at ?: '09:00', 0, 2);
                $current = (int) now()->timezone('Asia/Tashkent')->format('H');

                if ($configured !== $current) {
                    return self::SUCCESS;
                }
            }
        }

        $date = $this->option('date')
            ? \Illuminate\Support\Carbon::parse($this->option('date'))
            : now()->subDay();

        $rows = Transaction::withoutGlobalScopes()
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('paid_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->get(['amount', 'commission_amount', 'net_amount', 'psp_id']);

        $topPsp = $rows->groupBy('psp_id')
            ->map->sum('amount')
            ->sortDesc()
            ->keys()
            ->first();

        $payload = [
            'date' => $date->format('d M Y'),
            'count' => $rows->count(),
            'volume' => (int) $rows->sum('amount'),
            'commission' => (int) $rows->sum('commission_amount'),
            'net' => (int) $rows->sum('net_amount'),
            'top_psp' => $topPsp ? \App\Models\Psp::find($topPsp)?->name : null,
        ];

        $sent = Telegram::broadcast(
            Alerts::format(AlertEvent::DailySummary, $payload),
            AlertEvent::DailySummary->value,
            $rule?->telegramChat,
        );

        $this->info("Daily summary for {$payload['date']}: {$payload['count']} payments, sent to {$sent} chat(s).");

        return self::SUCCESS;
    }
}
