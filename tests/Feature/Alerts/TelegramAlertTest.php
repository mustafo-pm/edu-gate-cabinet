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
