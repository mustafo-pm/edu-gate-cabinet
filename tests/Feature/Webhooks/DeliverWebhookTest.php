<?php

declare(strict_types=1);

use App\Enums\PspStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Psp;
use App\Models\WebhookDelivery;
use App\Support\Webhooks;
use App\Support\WebhookUrl;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Http::fake() appends stubs and the first match wins, so a fresh factory
    // per test keeps one test's stub from answering the next one's request.
    Http::swap(new Factory);

    // Deterministic DNS: the guard still runs in full, it just does not depend
    // on this machine having a network or on what clickpay.uz happens to
    // resolve to today.
    WebhookUrl::resolveUsing(fn (string $host) => match ($host) {
        'clickpay.uz' => ['203.0.113.10'],
        'rebound.example' => ['127.0.0.1'],   // public name, private answer
        default => [],
    });

    $this->psp = Psp::create([
        'name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active,
        'webhook_url' => 'https://clickpay.uz/edugate/webhook',
        'webhook_secret' => 'whsec_test_secret',
        'webhook_enabled' => true,
    ]);
});

afterEach(function () {
    // Static seam — leaving it set would silently stub DNS for every later test.
    WebhookUrl::resolveUsing(null);
});

function deliver(Psp $psp, array $data = ['ok' => true]): void
{
    (new DeliverWebhook(
        pspId: $psp->id, event: Webhooks::PAYMENT_COMPLETED,
        eventId: 'evt-1', data: $data,
    ))->handle();
}

it('posts the event to the configured endpoint', function () {
    Http::fake(['clickpay.uz/*' => Http::response(['received' => true], 200)]);

    deliver($this->psp);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://clickpay.uz/edugate/webhook'
            && $body['event'] === 'payment.completed'
            && $body['id'] === 'evt-1'
            && isset($body['occurred_at'], $body['data']);
    });
});

it('signs the body so the PSP can prove the call came from us', function () {
    Http::fake(['clickpay.uz/*' => Http::response([], 200)]);

    deliver($this->psp);

    Http::assertSent(function ($request) {
        $timestamp = $request->header(Webhooks::TIMESTAMP_HEADER)[0];
        $signature = $request->header(Webhooks::SIGNATURE_HEADER)[0];

        // Recomputed exactly as a PSP would: over the raw body, with the
        // timestamp mixed in so a captured request cannot be replayed later.
        return hash_equals(
            Webhooks::signature('whsec_test_secret', $timestamp, $request->body()),
            $signature,
        );
    });
});

it('carries a stable delivery id so a retry is not mistaken for a new event', function () {
    Http::fake(['clickpay.uz/*' => Http::response([], 200)]);

    deliver($this->psp);

    Http::assertSent(fn ($request) => $request->header(Webhooks::DELIVERY_HEADER)[0] === 'evt-1');
});

it('records the attempt', function () {
    Http::fake(['clickpay.uz/*' => Http::response([], 200)]);

    deliver($this->psp);

    $log = WebhookDelivery::withoutGlobalScopes()->first();

    expect($log->succeeded)->toBeTrue()
        ->and($log->status_code)->toBe(200)
        ->and($log->event)->toBe('payment.completed')
        ->and($log->event_id)->toBe('evt-1');
});

it('records a failure and rethrows so the queue retries', function () {
    Http::fake(['clickpay.uz/*' => Http::response('nope', 500)]);

    expect(fn () => deliver($this->psp))->toThrow(RuntimeException::class);

    $log = WebhookDelivery::withoutGlobalScopes()->first();

    expect($log->succeeded)->toBeFalse()
        ->and($log->status_code)->toBe(500);
});

it('sends nothing when the PSP has switched delivery off', function () {
    Http::fake();

    $this->psp->update(['webhook_enabled' => false]);

    deliver($this->psp);

    Http::assertNothingSent();
    expect(WebhookDelivery::withoutGlobalScopes()->count())->toBe(0);
});

it('sends nothing when no secret has been issued', function () {
    Http::fake();

    // An unsigned callback is one a PSP has no way to trust, so we would rather
    // send nothing at all.
    $this->psp->forceFill(['webhook_secret' => null])->save();

    deliver($this->psp);

    Http::assertNothingSent();
});

/**
 * The check that matters most: the address is re-validated here, not only when
 * it was saved. A hostname that resolved to a public address an hour ago can
 * be re-pointed at the loopback interface at any time.
 */
it('refuses to call an address that now points inside the network', function () {
    Http::fake();

    // A hostname that looks perfectly public and answers 127.0.0.1 — the shape
    // of DNS rebinding. Validating only at save time would have let this
    // through, because at save time it could have answered anything.
    $this->psp->update(['webhook_url' => 'https://rebound.example/hook']);

    deliver($this->psp);

    Http::assertNothingSent();

    $log = WebhookDelivery::withoutGlobalScopes()->first();

    expect($log->succeeded)->toBeFalse()
        ->and($log->error)->toContain('blocked: private_address');
});

it('drops a blocked delivery quietly instead of filing a failed job', function () {
    Http::fake();

    $this->psp->update(['webhook_url' => 'https://rebound.example/hook']);

    // A misconfigured endpoint is the PSP's to fix, not an incident of ours.
    // Failing the job would write one row per payment into the queue's failure
    // table until somebody noticed; the delivery log already has it, and that
    // is the table the PSP can actually see.
    expect(fn () => deliver($this->psp))->not->toThrow(Throwable::class);

    expect(WebhookDelivery::withoutGlobalScopes()->count())->toBe(1);
});

it('refuses a bare private address at send time', function () {
    Http::fake();

    $this->psp->update(['webhook_url' => 'https://127.0.0.1/hook']);

    deliver($this->psp);

    Http::assertNothingSent();
});

it('does not follow redirects', function () {
    // A 302 could land on an internal address that the guard just cleared the
    // original URL of, so the client must not chase it.
    Http::fake(['clickpay.uz/*' => Http::response([], 302, ['Location' => 'http://127.0.0.1/'])]);

    expect(fn () => deliver($this->psp))->toThrow(RuntimeException::class);

    Http::assertSentCount(1);
});
