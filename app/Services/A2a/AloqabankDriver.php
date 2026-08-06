<?php

declare(strict_types=1);

namespace App\Services\A2a;

use App\Models\BankTransfer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Aloqabank — REST over HTTP Basic, per their API v2 document.
 *
 * Two behaviours from that document drive everything here:
 *
 *  1. Payments are ASYNCHRONOUS. `POST /payment` answers "Введен" (entered),
 *     not paid. Only a later `GET /payment/{orderId}` says "Проведен" (settled)
 *     or "Удален" (rejected by the core banking system).
 *
 *  2. On codes 1111 and 2222 the bank explicitly says DO NOT retry — query the
 *     order instead, because it may already exist on their side. A timeout is
 *     the same situation with no code attached. All three map to `Unknown`,
 *     never to `Failed`, because `Failed` is safe to resend and this is not.
 *
 * The base URL is the only thing that differs between the simulator and the
 * real bank: the client, the auth and the parsing are identical, which is the
 * whole point of running a simulator rather than a fake driver class.
 */
class AloqabankDriver implements A2aDriver
{
    /** Codes after which resending could pay twice. */
    private const MUST_QUERY = [1111, 2222];

    /*
     * The bank's own status strings. Declared here rather than borrowed from
     * the simulator: this driver talks to Aloqabank, and it must not depend on
     * a class that does not exist in production.
     */
    private const ENTERED = 'Введен';

    private const EXECUTED = 'Проведен';

    private const DELETED = 'Удален';

    public function key(): string
    {
        return 'aloqabank';
    }

    public function send(BankTransfer $transfer): A2aResult
    {
        $payload = $this->buildPayload($transfer);

        try {
            $response = $this->client()->post('/payment', $payload);
        } catch (ConnectionException $e) {
            // Timed out or refused. The bank may or may not have taken it.
            return A2aResult::unknown('Connection failed: '.$e->getMessage(), null, null, $payload);
        }

        if (! $response->successful()) {
            return A2aResult::unknown('HTTP '.$response->status(), ['body' => $response->body()], null, $payload);
        }

        $body = $response->json();

        if (! is_array($body)) {
            // A 200 with a body we cannot parse tells us nothing about the money.
            return A2aResult::unknown('Unparseable response body', ['body' => $response->body()], null, $payload);
        }

        return $this->interpret($body, $payload);
    }

    public function status(BankTransfer $transfer): A2aResult
    {
        try {
            $response = $this->client()->get('/payment/'.urlencode($transfer->reference));
        } catch (ConnectionException $e) {
            return A2aResult::unknown('Connection failed: '.$e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            return A2aResult::unknown('Unparseable response body', ['body' => $response->body()]);
        }

        return $this->interpret($body);
    }

    /** Map the bank's envelope onto one of our statuses. */
    private function interpret(array $body, ?array $request = null): A2aResult
    {
        $code = (int) ($body['code'] ?? 0);
        $docId = $body['data']['doc_id'] ?? null;

        if (($body['status'] ?? null) === 'error' || $code !== 0) {
            $message = $body['message'] ?? 'Bank returned code '.$code;

            return in_array($code, self::MUST_QUERY, true)
                ? A2aResult::unknown($message, $body, $code, $request)
                : A2aResult::rejected($code, $message, $body, null, $request);
        }

        return match ($body['data']['payment_status'] ?? null) {
            self::EXECUTED => A2aResult::settled($docId, $body, $request),
            self::DELETED => A2aResult::rejected(null, 'Rejected by the core banking system', $body, $docId, $request),
            self::ENTERED => A2aResult::accepted($docId, $body, $request),
            // Success envelope with a status we do not recognise: do not guess
            // that the money moved.
            default => A2aResult::unknown('Unrecognised payment_status', $body, null, $request),
        };
    }

    /**
     * The `payment` (by requisites) payload. `orderId` is our own reference,
     * which is unique per transfer and is what makes a retry safe on their side
     * too — the bank refuses a duplicate order id.
     */
    private function buildPayload(BankTransfer $transfer): array
    {
        return [
            'orderId' => $transfer->reference,
            'amount' => (string) $transfer->amount,        // tiyin, as strings per their examples
            'comissionAmount' => '0',                      // their spelling, not ours
            'purpose' => $transfer->purpose_text ?? 'Tuition settlement',
            'serviceId' => (string) $this->config('service_id'),
            'receiverName' => $transfer->recipient_name,
            'mfoReceiver' => $transfer->recipient_mfo,
            'receiverAccount' => $transfer->recipient_account,
            'innReceiver' => $transfer->recipient_tax,
        ];
    }

    private function client()
    {
        $base = rtrim((string) $this->config('base_url'), '/');

        if ($base === '') {
            throw new RuntimeException('Aloqabank base URL is not configured.');
        }

        // A production cabinet pointed at a simulator would report transfers as
        // successful while nothing moved. Fail loudly instead.
        if (app()->isProduction() && str_contains($base, '/sim/')) {
            throw new RuntimeException(
                'Refusing to send live transfers to the Aloqabank SIMULATOR. Fix ALOQABANK_BASE_URL.'
            );
        }

        return Http::baseUrl($base)
            ->withBasicAuth((string) $this->config('username'), (string) $this->config('password'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) $this->config('timeout', 15))
            // No automatic retries: a repeated POST after a timeout is exactly
            // the double-payment this whole class is arranged to avoid.
            ->withoutRedirecting();
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("services.aloqabank.{$key}", $default);
    }
}
