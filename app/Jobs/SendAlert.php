<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AlertEvent;
use App\Support\Alerts;
use App\Support\Telegram;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one alert to Telegram.
 *
 * Dispatched with ->afterCommit() (see Alerts::raise): alerts are raised from
 * inside money transactions, and a rolled-back payment must never announce
 * itself. Do NOT redeclare $afterCommit here — the Queueable trait already
 * defines it as ?bool and a narrower type is a fatal composition error.
 */
class SendAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public string $event,
        public array $payload = [],
    ) {}

    public function handle(): void
    {
        $event = AlertEvent::tryFrom($this->event);
        if (! $event) {
            return;
        }

        try {
            // A rule may pin its alert to one topic; otherwise broadcast.
            $target = \App\Models\AlertRule::for($event)?->telegramChat;

            Telegram::broadcast(Alerts::format($event, $this->payload), $event->value, $target);
        } catch (\Throwable $e) {
            // Swallow: an alert must never take down the request that raised it.
            Log::warning('SendAlert failed', ['event' => $this->event, 'error' => $e->getMessage()]);
        }
    }
}
