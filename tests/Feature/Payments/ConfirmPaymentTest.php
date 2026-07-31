<?php

declare(strict_types=1);

use App\Actions\Payments\CheckPayment;
use App\Actions\Payments\ConfirmPayment;
use App\Enums\CommissionScope;
use App\Enums\LedgerType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\ScheduleStatus;
use App\Enums\StudentStatus;
use App\Exceptions\PaymentException;
use App\Models\CommissionRule;
use App\Models\Deposit;
use App\Models\Merchant;
use App\Models\PaymentSchedule;
use App\Models\Psp;
use App\Models\Student;
use App\Models\Transaction;

/** Build a PSP with an opening deposit, plus a merchant/student/schedule. */
function seedPaymentWorld(int $depositTiyin = 10_000_000, int $scheduleTiyin = 6_000_000): array
{
    CommissionRule::create([
        'scope' => CommissionScope::Global,
        'rate_bps' => 150, // 1.5%
        'fixed_fee' => 0,
        'priority' => 10,
        'is_active' => true,
    ]);

    $psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);

    Deposit::create([
        'psp_id' => $psp->id,
        'type' => LedgerType::Credit,
        'amount' => $depositTiyin,
        'balance_after' => $depositTiyin,
        'reference' => 'TOPUP',
    ]);

    $merchant = Merchant::create([
        'name' => 'TDU', 'type' => MerchantType::University, 'status' => MerchantStatus::Active,
        'commission_bps' => 150,
    ]);

    $student = Student::create([
        'merchant_id' => $merchant->id,
        'student_id_number' => 'STU-0001',
        'first_name' => 'Jasur', 'last_name' => 'Toshmatov',
        'status' => StudentStatus::Active,
    ]);

    $schedule = PaymentSchedule::create([
        'merchant_id' => $merchant->id,
        'student_id' => $student->id,
        'title' => 'Tuition', 'period' => '2026-09',
        'amount' => $scheduleTiyin, 'paid_amount' => 0,
        'due_date' => '2026-09-10', 'status' => ScheduleStatus::Unpaid,
    ]);

    return compact('psp', 'merchant', 'student', 'schedule');
}

it('debits the deposit, computes commission and settles the schedule atomically', function () {
    ['psp' => $psp, 'merchant' => $merchant, 'student' => $student, 'schedule' => $schedule] = seedPaymentWorld();

    $check = app(CheckPayment::class)->handle($merchant->id, $student->student_id_number);

    $txn = app(ConfirmPayment::class)->handle(
        pspId: $psp->id,
        checkId: $check['check_id'],
        partnerTransactionId: 'PSP-TXN-1',
        amountTiyin: 6_000_000,
        idempotencyKey: 'idem-1',
    );

    // Commission = 1.5% of 6,000,000 = 90,000; net = 5,910,000.
    expect($txn->amount)->toBe(6_000_000)
        ->and($txn->commission_amount)->toBe(90_000)
        ->and($txn->net_amount)->toBe(5_910_000);

    // Deposit debited by the full amount → 10,000,000 - 6,000,000 = 4,000,000.
    expect($psp->fresh()->depositBalance())->toBe(4_000_000);

    // Ledger has exactly one debit row for this transaction.
    expect(Deposit::where('transaction_id', $txn->id)->where('type', LedgerType::Debit)->count())->toBe(1);

    // Schedule fully paid.
    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Paid)
        ->and($schedule->fresh()->paid_amount)->toBe(6_000_000);
});

it('is idempotent on (psp_id, partner_transaction_id) — no double debit', function () {
    ['psp' => $psp, 'merchant' => $merchant, 'student' => $student] = seedPaymentWorld();

    $check = app(CheckPayment::class)->handle($merchant->id, $student->student_id_number);

    $first = app(ConfirmPayment::class)->handle($psp->id, $check['check_id'], 'PSP-TXN-9', 6_000_000, 'idem-9');
    // Replay with the SAME partner transaction id.
    $second = app(ConfirmPayment::class)->handle($psp->id, $check['check_id'], 'PSP-TXN-9', 6_000_000, 'idem-9');

    expect($second->id)->toBe($first->id);
    expect(Transaction::count())->toBe(1);
    // Debited only once.
    expect($psp->fresh()->depositBalance())->toBe(4_000_000);
});

it('refuses a payment when the deposit balance is insufficient', function () {
    ['psp' => $psp, 'merchant' => $merchant, 'student' => $student] = seedPaymentWorld(depositTiyin: 1_000_000);

    $check = app(CheckPayment::class)->handle($merchant->id, $student->student_id_number);

    app(ConfirmPayment::class)->handle($psp->id, $check['check_id'], 'PSP-TXN-2', 6_000_000, 'idem-2');
})->throws(PaymentException::class);

it('does not create a transaction or debit when the balance is insufficient', function () {
    ['psp' => $psp, 'merchant' => $merchant, 'student' => $student] = seedPaymentWorld(depositTiyin: 1_000_000);
    $check = app(CheckPayment::class)->handle($merchant->id, $student->student_id_number);

    try {
        app(ConfirmPayment::class)->handle($psp->id, $check['check_id'], 'PSP-TXN-3', 6_000_000, 'idem-3');
    } catch (PaymentException) {
        // expected
    }

    expect(Transaction::count())->toBe(0);
    expect($psp->fresh()->depositBalance())->toBe(1_000_000); // untouched
});
