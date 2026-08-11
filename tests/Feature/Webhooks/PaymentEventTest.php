<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Jobs\DeliverWebhook;
use App\Models\PspUser;
use App\Models\Transaction;
use App\Support\Webhooks;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\withHeaders;

/**
 * A confirmed payment tells the PSP.
 *
 * The event is queued, never sent inline: a slow or broken PSP endpoint must
 * not be able to hold up — or fail — a payment whose money has already moved.
 */
it('queues a payment.completed event once the payment is confirmed', function () {
    Queue::fake();

    $w = apiWorld();
    $w['psp']->update([
        'webhook_url' => 'https://clickpay.uz/hook',
        'webhook_secret' => 'whsec_x',
        'webhook_enabled' => true,
    ]);

    $token = tokenFor($w);

    $check = withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/payments/check', [
            'institution_id' => $w['merchant']->id,
            'student_id_number' => 'STU-0001',
        ])->assertOk();

    withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'idem-hook-1'])
        ->postJson('/api/v1/payments/confirm', [
            'check_id' => $check->json('data.check_id'),
            'partner_transaction_id' => 'PT-HOOK-1',
            'amount' => 6_000_000,
        ])->assertCreated();

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === Webhooks::PAYMENT_COMPLETED
            && $job->data['partner_transaction_id'] === 'PT-HOOK-1'
            && $job->data['amount'] === 6_000_000
            && $job->data['status'] === 'completed';
    });
});

it('queues nothing for a PSP that has not configured an endpoint', function () {
    Queue::fake();

    $w = apiWorld();   // no webhook settings at all
    $token = tokenFor($w);

    $check = withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/payments/check', [
            'institution_id' => $w['merchant']->id,
            'student_id_number' => 'STU-0001',
        ])->assertOk();

    withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'idem-hook-2'])
        ->postJson('/api/v1/payments/confirm', [
            'check_id' => $check->json('data.check_id'),
            'partner_transaction_id' => 'PT-HOOK-2',
            'amount' => 6_000_000,
        ])->assertCreated();

    Queue::assertNotPushed(DeliverWebhook::class);
});

it('keeps our commission out of what the PSP is told', function () {
    // The PSP is party to this payment, so unlike the public receipt they do
    // see commission and net — they need both to reconcile their own ledger.
    $w = apiWorld();
    $w['psp']->update([
        'webhook_url' => 'https://clickpay.uz/hook',
        'webhook_secret' => 'whsec_x', 'webhook_enabled' => true,
    ]);

    $txn = new Transaction([
        'partner_transaction_id' => 'PT-1', 'amount' => 6_000_000,
        'commission_amount' => 90_000, 'net_amount' => 5_910_000,
        'status' => TransactionStatus::Completed,
    ]);
    $txn->id = 1;

    $payload = Webhooks::transactionPayload(Webhooks::PAYMENT_COMPLETED, $txn);

    expect($payload)->toHaveKeys(['commission_amount', 'net_amount'])
        // But never anything about other tenants or our internals.
        ->and($payload)->not->toHaveKeys(['merchant_id', 'psp_id', 'student_id', 'idempotency_key']);
});

it('renders the webhook settings page for a signed-in PSP user', function () {
    $w = apiWorld();

    $user = PspUser::create([
        'psp_id' => $w['psp']->id, 'name' => 'Ops', 'email' => 'ops@clickpay.uz',
        'password' => Hash::make('secret-password'),
        'is_active' => true, 'password_changed_at' => now(),
    ]);

    Pest\Laravel\actingAs($user, 'psp')
        ->get('/partner/webhooks')
        ->assertOk()
        ->assertSee(__('cabinet.webhooks.endpoint'));
});
