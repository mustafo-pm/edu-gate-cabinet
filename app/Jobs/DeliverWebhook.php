<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Psp;
use App\Models\WebhookDelivery;
use App\Support\Webhooks;
use App\Support\WebhookUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one event to one PSP, and records every attempt.
 *
 * Retries with a widening backoff: a PSP endpoint is usually down for minutes,
 * not milliseconds, and hammering it adds nothing. Deliberately never more than
 * a handful of tries — a webhook is a courtesy, and the API is the source of
 * truth. A PSP that missed one asks GET /payments/{id}.
 *
 * Do NOT redeclare $afterCommit here; the Queueable trait already defines it.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** 1 min, 5 min, 30 min. */
    public array $backoff = [60, 300, 1800];

    public function __construct(
        public int $pspId,
        public string $event,
        public string $eventId,
        public array $data,
        public ?int $transactionId = null,
    ) {}

    public function handle(): void
    {
        $psp = Psp::find($this->pspId);

        // Configuration can change between queueing and running — a PSP that
        // switched their endpoint off should stop receiving, not drain a queue.
        if (! Webhooks::configured($psp)) {
            return;
        }

        $url = (string) $psp->webhook_url;

        // Re-checked here, not just when it was saved. A hostname that resolved
        // to a public address at save time can be re-pointed at 127.0.0.1
        // afterwards, and this job is the thing that would make the request.
        if ($reason = WebhookUrl::reject($url)) {
            $this->record($psp, $url, null, "blocked: {$reason}", 0, false);

            // Recorded and dropped, not retried and not failed.
            //
            // No retry, because a blocked address will still be blocked in five
            // minutes and re-resolving an attacker-controlled name is itself the
            // behaviour we are avoiding.
            //
            // And no failed job either: a misconfigured endpoint is the PSP's to
            // fix, not an incident of ours, and marking it failed would file one
            // row per payment into the queue's failure table until somebody
            // noticed. It is already in webhook_deliveries, which is the table
            // the PSP can actually see.
            return;
        }

        $body = json_encode($this->envelope(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                Webhooks::SIGNATURE_HEADER => Webhooks::signature((string) $psp->webhook_secret, $timestamp, $body),
                Webhooks::TIMESTAMP_HEADER => $timestamp,
                Webhooks::EVENT_HEADER => $this->event,
                Webhooks::DELIVERY_HEADER => $this->eventId,
                'Content-Type' => 'application/json',
                'User-Agent' => 'EduGate-Webhooks/1.0',
            ])
                ->timeout(10)
                ->connectTimeout(5)
                // A redirect could land on an internal address that the checks
                // above just cleared the original URL of.
                ->withoutRedirecting()
                ->withBody($body, 'application/json')
                ->post($url);

            $ms = (int) ((microtime(true) - $startedAt) * 1000);

            $this->record($psp, $url, $response->status(), null, $ms, $response->successful());

            if (! $response->successful()) {
                throw new \RuntimeException("PSP responded {$response->status()}");
            }
        } catch (\Throwable $e) {
            if (! isset($ms)) {
                $this->record(
                    $psp, $url, null, $e->getMessage(),
                    (int) ((microtime(true) - $startedAt) * 1000), false,
                );
            }

            throw $e;
        }
    }

    /** What the PSP actually receives. */
    private function envelope(): array
    {
        return [
            'id' => $this->eventId,
            'event' => $this->event,
            'occurred_at' => now()->toIso8601String(),
            'data' => $this->data,
        ];
    }

    private function record(
        Psp $psp, string $url, ?int $status, ?string $error, int $ms, bool $ok,
    ): void {
        WebhookDelivery::withoutGlobalScopes()->create([
            'psp_id' => $psp->id,
            'event_id' => $this->eventId,
            'event' => $this->event,
            'transaction_id' => $this->transactionId,
            'url' => $url,
            'payload' => $this->envelope(),
            'attempt' => $this->attempts(),
            'status_code' => $status,
            // Truncated: a PSP returning an HTML error page would otherwise
            // write kilobytes per attempt into a table we keep forever.
            'error' => $error === null ? null : mb_substr($error, 0, 500),
            'duration_ms' => $ms,
            'succeeded' => $ok,
        ]);
    }
}
