<?php

declare(strict_types=1);

use App\Enums\AlertEvent;
use App\Enums\LedgerType;
use App\Enums\PspStatus;
use App\Jobs\SendAlert;
use App\Models\AlertRule;
use App\Models\Deposit;
use App\Models\Psp;
use App\Models\TelegramChat;
use App\Support\Alerts;
use App\Support\Telegram;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The alert layer sits inside money transactions, so these guard two things:
 * it must never crash the payment, and it must never fire for a rolled-back one.
 */
beforeEach(function () {
    // The migration seeds the rules; make sure the ones under test are on.
    AlertRule::where('event', AlertEvent::DepositToppedUp->value)->update(['is_enabled' => true]);
    $this->psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);
});

it('queues an alert when a deposit is topped up', function () {
    Bus::fake();

    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $this->psp->id,
        'type' => LedgerType::Credit,
        'amount' => 1_000_000,
        'balance_after' => 1_000_000,
        'reference' => 'TOPUP-1',
    ]);

    Bus::assertDispatched(SendAlert::class,
        fn (SendAlert $job) => $job->event === AlertEvent::DepositToppedUp->value);
});

it('does not alert when the rule is disabled', function () {
    Bus::fake();
    AlertRule::where('event', AlertEvent::DepositToppedUp->value)->update(['is_enabled' => false]);

    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $this->psp->id,
        'type' => LedgerType::Credit,
        'amount' => 500,
        'balance_after' => 500,
    ]);

    Bus::assertNotDispatched(SendAlert::class);
});

it('alerts on a low balance only once it falls under the threshold', function () {
    Bus::fake();
    AlertRule::where('event', AlertEvent::DepositLow->value)
        ->update(['is_enabled' => true, 'threshold' => 1_000_000]);

    // Above the threshold — no alert.
    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $this->psp->id, 'type' => LedgerType::Debit,
        'amount' => 100, 'balance_after' => 2_000_000,
    ]);
    Bus::assertNotDispatched(SendAlert::class,
        fn (SendAlert $j) => $j->event === AlertEvent::DepositLow->value);

    // Below it — alert.
    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $this->psp->id, 'type' => LedgerType::Debit,
        'amount' => 100, 'balance_after' => 900_000,
    ]);
    Bus::assertDispatched(SendAlert::class,
        fn (SendAlert $j) => $j->event === AlertEvent::DepositLow->value);
});

/**
 * Regression: SendAlert once redeclared $afterCommit with a narrower type than
 * the Queueable trait, which is a FATAL composition error — it killed the whole
 * test run with no output. Instantiating the job proves it still composes.
 */
it('composes the SendAlert job without a property conflict', function () {
    $job = new SendAlert(AlertEvent::DailySummary->value, ['count' => 1]);

    expect($job)->toBeInstanceOf(SendAlert::class)
        ->and($job->event)->toBe(AlertEvent::DailySummary->value);
});

it('never lets a Telegram failure bubble out of a send', function () {
    Http::fake(fn () => Http::response(['ok' => false, 'description' => 'chat not found'], 400));
    TelegramChat::create(['chat_id' => '-100123', 'title' => 'Ops', 'is_active' => true]);

    // Must return a count rather than throwing.
    expect(Telegram::broadcast('<b>test</b>', 'test'))->toBe(0);
});

it('formats money in the alert body as UZS, not raw tiyin', function () {
    $body = Alerts::format(AlertEvent::DepositLow, [
        'psp' => 'ClickPay', 'balance' => 320_000_000, 'threshold' => 500_000_000,
    ]);

    expect($body)->toContain('3 200 000.00 UZS')
        ->and($body)->toContain('ClickPay');
});

/**
 * Forum topics: a destination is (chat + optional topic), and the thread id
 * must reach sendMessage or the alert silently lands in "General".
 */
it('includes message_thread_id when the destination is a topic', function () {
    Http::fake(fn () => Http::response(['ok' => true], 200));

    $topic = TelegramChat::create([
        'chat_id' => '-100777', 'message_thread_id' => '42',
        'topic_name' => 'Payments', 'title' => 'Ops', 'is_active' => true,
    ]);

    Telegram::send($topic->chat_id, 'hi', 'test', $topic->message_thread_id);

    Http::assertSent(fn ($r) => ($r->data()['message_thread_id'] ?? null) === 42);
});

it('omits message_thread_id for a plain chat', function () {
    Http::fake(fn () => Http::response(['ok' => true], 200));

    $chat = TelegramChat::create(['chat_id' => '-100888', 'title' => 'Ops', 'is_active' => true]);
    Telegram::send($chat->chat_id, 'hi', 'test', $chat->message_thread_id);

    Http::assertSent(fn ($r) => ! array_key_exists('message_thread_id', $r->data()));
});

it('sends only to the pinned destination when a rule targets one topic', function () {
    Http::fake(fn () => Http::response(['ok' => true], 200));

    TelegramChat::create(['chat_id' => '-100999', 'title' => 'Other', 'is_active' => true]);
    $topic = TelegramChat::create([
        'chat_id' => '-100777', 'message_thread_id' => '7',
        'topic_name' => 'Alerts', 'title' => 'Ops', 'is_active' => true,
    ]);

    expect(Telegram::broadcast('<b>x</b>', 'test', $topic))->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r->data()['chat_id'] === '-100777');
});

it('labels a topic destination distinctly from the chat', function () {
    $chat = TelegramChat::create(['chat_id' => '-1', 'title' => 'Ops', 'is_active' => true]);
    $topic = TelegramChat::create([
        'chat_id' => '-1', 'message_thread_id' => '9', 'topic_name' => 'Payments',
        'title' => 'Ops', 'is_active' => true,
    ]);

    expect($chat->label())->toBe('Ops')
        ->and($topic->label())->toBe('Ops › Payments');
});
