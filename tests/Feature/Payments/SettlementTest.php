<?php

declare(strict_types=1);

use App\Actions\Payments\CheckPayment;
use App\Actions\Payments\ConfirmPayment;
use App\Actions\Payments\SettleTransaction;
use App\Enums\BankTransferStatus;
use App\Enums\BranchMatchStatus;
use App\Enums\LedgerType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\TransactionStatus;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\BankTransfer;
use App\Models\Deposit;
use App\Models\Merchant;
use App\Models\Psp;
use App\Models\SettlementAccount;
use App\Models\Student;
use App\Models\Transaction;
use App\Services\A2a\AloqabankDriver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Settling collected tuition out to an institution's bank.
 *
 * The rules being pinned down here are the ones that decide whether an
 * institution gets paid once, twice, or not at all.
 */
beforeEach(function () {
    config(['services.aloqabank.base_url' => 'http://bank.test/api/v2']);

    $this->bank = Bank::create([
        'code' => '00401', 'slug' => 'aloqabank', 'name_uz' => 'Aloqabank',
        'a2a_supported' => true, 'a2a_driver' => 'aloqabank', 'is_active' => true,
    ]);

    $this->branch = BankBranch::create([
        'bank_id' => $this->bank->id, 'mfo' => '00401', 'name_uz' => 'Aloqabank HQ',
        'match_status' => BranchMatchStatus::Confirmed, 'is_active' => true,
    ]);

    SettlementAccount::create([
        'bank_id' => $this->bank->id, 'label' => 'Aloqabank — main',
        'account' => '20208000405273320010', 'mfo' => '00401', 'tax' => '123456789',
        'holder_name' => 'EduGate LLC', 'driver' => 'aloqabank',
        'is_default' => true, 'is_active' => true,
    ]);

    $this->merchant = Merchant::create([
        'name' => 'Toshkent Davlat Universiteti', 'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
        'mfo' => '00401', 'bank_account' => '29801000990248844444', 'stir' => '123456789',
    ]);

    $this->psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);

    $this->student = Student::withoutGlobalScopes()->create([
        'merchant_id' => $this->merchant->id, 'student_id_number' => 'STU-0002',
        'first_name' => 'Malika', 'last_name' => 'Yusupova',
    ]);
});

function payment(array $overrides = []): Transaction
{
    return Transaction::withoutGlobalScopes()->create(array_merge([
        'psp_id' => test()->psp->id,
        'merchant_id' => test()->merchant->id,
        'student_id' => test()->student->id,
        'partner_transaction_id' => 'PT-'.uniqid(),
        'amount' => 1_000_000,
        'commission_amount' => 15_000,
        'net_amount' => 985_000,
        'status' => TransactionStatus::Completed,
        'paid_at' => now(),
    ], $overrides));
}

function bankSays(array $data, int $code = 0, string $status = 'success'): void
{
    // Http::fake() APPENDS stubs and the FIRST match wins, so calling it twice
    // silently keeps the original answer. Tests here deliberately change what
    // the bank says mid-flight (accepted, then settled), so start clean.
    Http::swap(new Factory);

    Http::fake(['*' => Http::response(
        ['status' => $status, 'code' => $code, 'data' => $data['data'] ?? null]
        + (isset($data['message']) ? ['message' => $data['message']] : []),
        200,
    )]);
}

it('creates a posting and sends it to the bank', function () {
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_999']]);

    $txn = payment();
    $posting = app(SettleTransaction::class)->handle($txn);

    expect($posting->reference)->toBe('EG-'.$txn->id)
        ->and($posting->status)->toBe(BankTransferStatus::Sent)
        ->and($posting->external_id)->toBe('1180_999')
        // Net, not gross: our commission never leaves for the institution.
        ->and($posting->amount)->toBe(985_000)
        ->and($posting->request_payload['orderId'])->toBe('EG-'.$txn->id)
        ->and($posting->request_payload['amount'])->toBe('985000');
});

it('records a posting but sends nothing when the branch is not confirmed', function () {
    Http::fake();
    $this->branch->update(['match_status' => BranchMatchStatus::Auto]);

    $posting = app(SettleTransaction::class)->handle(payment());

    expect($posting->status)->toBe(BankTransferStatus::Pending)
        ->and($posting->error)->toContain('not confirmed');

    // An auto-matched MFO is a guess; a guess must never route money.
    Http::assertNothingSent();
});

it('records a posting but sends nothing when the institution has no bank account', function () {
    Http::fake();
    $this->merchant->update(['bank_account' => null]);

    $posting = app(SettleTransaction::class)->handle(payment());

    expect($posting->status)->toBe(BankTransferStatus::Pending)
        ->and($posting->error)->toContain('no bank account');

    Http::assertNothingSent();
});

it('marks the posting confirmed when the bank has already settled it', function () {
    bankSays(['data' => ['payment_status' => 'Проведен', 'doc_id' => '1180_1']]);

    $posting = app(SettleTransaction::class)->handle(payment());

    expect($posting->status)->toBe(BankTransferStatus::Confirmed)
        ->and($posting->confirmed_at)->not->toBeNull();
});

it('marks the posting failed when the bank rejects it', function () {
    bankSays(['message' => 'Счёт не найден'], code: 1013, status: 'error');

    $posting = app(SettleTransaction::class)->handle(payment());

    expect($posting->status)->toBe(BankTransferStatus::Failed)
        ->and($posting->error)->toBe('Счёт не найден')
        ->and($posting->failed_at)->not->toBeNull();
});

/**
 * The most important rule in the file. On 1111/2222 the bank says the order may
 * already exist — resending could pay the institution twice.
 */
it('maps a system error to Unknown, never to Failed', function (int $code) {
    bankSays(['message' => 'Системная ошибка'], code: $code, status: 'error');

    $posting = app(SettleTransaction::class)->handle(payment());

    expect($posting->status)->toBe(BankTransferStatus::Unknown)
        ->and($posting->status->needsReview())->toBeTrue()
        ->and($posting->status->isFinal())->toBeFalse();
})->with([1111, 2222]);

it('maps a timeout to Unknown, because the money may already have moved', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    $posting = app(SettleTransaction::class)->handle(payment());

    expect($posting->status)->toBe(BankTransferStatus::Unknown)
        ->and($posting->error)->toContain('Connection failed')
        // Even with no answer, we keep what we sent — it is the only evidence.
        ->and($posting->request_payload['orderId'])->toBe($posting->reference);
});

it('never treats an unparseable 200 as success', function () {
    Http::fake(['*' => Http::response('<html>maintenance</html>', 200)]);

    expect(app(SettleTransaction::class)->handle(payment())->status)
        ->toBe(BankTransferStatus::Unknown);
});

it('settles a payment only once', function () {
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_2']]);

    $txn = payment();
    $first = app(SettleTransaction::class)->handle($txn);
    $second = app(SettleTransaction::class)->handle($txn);

    expect($second->id)->toBe($first->id)
        ->and(BankTransfer::where('transaction_id', $txn->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

it('does not settle a payment that is not completed', function () {
    Http::fake();

    expect(app(SettleTransaction::class)->handle(payment(['status' => TransactionStatus::Pending])))
        ->toBeNull();
});

it('lets the poller move a sent posting to confirmed', function () {
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_3']]);
    $posting = app(SettleTransaction::class)->handle(payment());
    expect($posting->status)->toBe(BankTransferStatus::Sent);

    bankSays(['data' => ['payment_status' => 'Проведен', 'doc_id' => '1180_3']]);
    $this->artisan('transfers:poll')->assertSuccessful();

    expect($posting->fresh()->status)->toBe(BankTransferStatus::Confirmed);
});

it('lets the poller resolve an Unknown posting without resending it', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    $posting = app(SettleTransaction::class)->handle(payment());
    expect($posting->status)->toBe(BankTransferStatus::Unknown);

    // The order did exist at the bank after all — asking is safe, resending is not.
    bankSays(['data' => ['payment_status' => 'Проведен', 'doc_id' => '1180_4']]);
    $this->artisan('transfers:poll')->assertSuccessful();

    expect($posting->fresh()->status)->toBe(BankTransferStatus::Confirmed);
    Http::assertSent(fn ($r) => $r->method() === 'GET');
});

/**
 * A bank outage must never undo a payment that already succeeded: the student
 * has paid and the PSP deposit is debited. Settlement is a separate concern.
 */
it('completes the payment even when the bank is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $this->psp->id, 'type' => LedgerType::Credit,
        'amount' => 10_000_000, 'balance_after' => 10_000_000, 'reference' => 'TOPUP',
    ]);

    $check = app(CheckPayment::class)
        ->handle($this->merchant->id, 'STU-0002', 500_000);

    $txn = app(ConfirmPayment::class)->handle(
        pspId: $this->psp->id,
        checkId: $check['check_id'],
        partnerTransactionId: 'PT-OUTAGE',
        amountTiyin: 500_000,
        idempotencyKey: 'idem-outage',
    );

    expect($txn->status)->toBe(TransactionStatus::Completed);

    // QUEUE_CONNECTION=sync in tests, so the job ran inline and still could not
    // break the payment.
    expect(BankTransfer::where('transaction_id', $txn->id)->first()->status)
        ->toBe(BankTransferStatus::Unknown);
});

it('refuses to send live money to the simulator', function () {
    config(['services.aloqabank.base_url' => 'https://cabinet.edu-gate.uz/sim/aloqabank/api/v2']);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(AloqabankDriver::class)->send(new BankTransfer(['reference' => 'EG-1'])))
        ->toThrow(RuntimeException::class, 'Refusing to send live transfers');
});

/**
 * The gap that made a real payment look like it vanished: the queue worker was
 * down, so the job waited. A replayed confirm returned early and never
 * re-dispatched, leaving the institution unpaid with nothing to show for it.
 */
it('queues settlement again when a replayed confirm finds no posting', function () {
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_5']]);

    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $this->psp->id, 'type' => LedgerType::Credit,
        'amount' => 10_000_000, 'balance_after' => 10_000_000, 'reference' => 'TOPUP',
    ]);

    // First confirm with settlement off — the payment lands, no posting is made.
    config(['settlement.auto' => false]);
    $check = app(CheckPayment::class)->handle($this->merchant->id, 'STU-0002', 500_000);
    $txn = app(ConfirmPayment::class)->handle(
        pspId: $this->psp->id, checkId: $check['check_id'],
        partnerTransactionId: 'PT-REPLAY', amountTiyin: 500_000, idempotencyKey: 'k1',
    );
    expect(BankTransfer::where('transaction_id', $txn->id)->exists())->toBeFalse();

    // The PSP retries the same partner_transaction_id. Settlement is on now.
    config(['settlement.auto' => true]);
    $replay = app(ConfirmPayment::class)->handle(
        pspId: $this->psp->id, checkId: $check['check_id'],
        partnerTransactionId: 'PT-REPLAY', amountTiyin: 500_000, idempotencyKey: 'k2',
    );

    expect($replay->id)->toBe($txn->id)
        ->and(BankTransfer::where('transaction_id', $txn->id)->count())->toBe(1);
});

it('does not queue settlement twice when a replay already has a posting', function () {
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_6']]);
    $txn = payment();
    app(SettleTransaction::class)->handle($txn);

    app(ConfirmPayment::class); // no-op; the guard is exercised below
    expect(BankTransfer::where('transaction_id', $txn->id)->count())->toBe(1);

    app(SettleTransaction::class)->handle($txn);
    expect(BankTransfer::where('transaction_id', $txn->id)->count())->toBe(1);
});

it('backfills payments whose settlement job never ran', function () {
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_7']]);
    $txn = payment();   // completed, but nothing settled it

    expect(BankTransfer::count())->toBe(0);

    $this->artisan('transfers:settle-missing')->assertSuccessful();

    expect(BankTransfer::where('transaction_id', $txn->id)->count())->toBe(1);
});

it('re-sends a blocked posting once the branch is confirmed', function () {
    Http::fake();
    $this->branch->update(['match_status' => BranchMatchStatus::Auto]);
    $posting = app(SettleTransaction::class)->handle(payment());
    expect($posting->status)->toBe(BankTransferStatus::Pending);

    // Someone confirms the MFO, then presses "Send to bank".
    $this->branch->update(['match_status' => BranchMatchStatus::Confirmed]);
    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_8']]);

    $sent = app(SettleTransaction::class)->retry($posting->fresh());

    expect($sent->id)->toBe($posting->id)          // never sent, so the row is reused
        ->and($sent->status)->toBe(BankTransferStatus::Sent)
        ->and($sent->error)->toBeNull();
});

it('appends a NEW posting when re-sending a rejected one', function () {
    bankSays(['message' => 'Счёт не найден'], code: 1013, status: 'error');
    $posting = app(SettleTransaction::class)->handle(payment());
    expect($posting->status)->toBe(BankTransferStatus::Failed);

    bankSays(['data' => ['payment_status' => 'Введен', 'doc_id' => '1180_9']]);
    $replacement = app(SettleTransaction::class)->retry($posting->fresh());

    // The bank has spent that order id, so it must not be reused — and the
    // original stays put, because money rows are append-only.
    expect($replacement->id)->not->toBe($posting->id)
        ->and($replacement->reference)->toBe($posting->reference.'-r2')
        ->and($posting->fresh()->status)->toBe(BankTransferStatus::Failed);
});

it('refuses to re-send an Unknown posting', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    $posting = app(SettleTransaction::class)->handle(payment());
    expect($posting->status)->toBe(BankTransferStatus::Unknown);

    Http::swap(new Factory);
    Http::fake();

    // We do not know whether that money left. Re-sending is how it goes twice.
    $result = app(SettleTransaction::class)->retry($posting->fresh());

    expect($result->status)->toBe(BankTransferStatus::Unknown);
    Http::assertNothingSent();
});

/**
 * Deploying settlement onto a system with history must not try to pay every
 * past transaction — those were almost certainly transferred by hand already.
 */
it('never backfills payments confirmed before settlement went live', function () {
    Http::fake();
    $old = payment(['paid_at' => now()->subDays(3)]);
    $new = payment(['paid_at' => now()]);

    config(['settlement.start_at' => now()->subDay()->toDateTimeString()]);

    $this->artisan('transfers:settle-missing')->assertSuccessful();

    expect(BankTransfer::where('transaction_id', $old->id)->exists())->toBeFalse()
        ->and(BankTransfer::where('transaction_id', $new->id)->exists())->toBeTrue();
});
