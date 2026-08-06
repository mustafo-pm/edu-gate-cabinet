<?php

declare(strict_types=1);

use App\Enums\BankTransferStatus;
use App\Enums\BranchMatchStatus;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\TransactionStatus;
use App\Filament\Pages\BankDrivers;
use App\Filament\Resources\BankTransfers\BankTransferResource;
use App\Models\AdminUser;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\BankTransfer;
use App\Models\Merchant;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Psp;
use App\Models\SettlementAccount;
use App\Models\Student;
use App\Models\Transaction;
use Database\Seeders\AccountingDemoSeeder;

use function Pest\Laravel\actingAs;

/**
 * The accounting register exists to answer one question — "which payment is
 * this provodka for?" — and to never let anyone edit a sent posting.
 */
function admin(): AdminUser
{
    return AdminUser::firstOrCreate(
        ['email' => 'acct@edu-gate.uz'],
        ['name' => 'Accountant', 'password' => bcrypt('password'), 'is_active' => true],
    );
}

/**
 * Minimal but real chain: bank → branch → our account, and psp → student →
 * payment. The demo seeder builds on the main seeder's data, which
 * RefreshDatabase wipes, so the tests stand up their own.
 */
function accountingFixtures(int $payments = 4): void
{
    $bank = Bank::create([
        'code' => '00401', 'slug' => 'aloqabank', 'name_uz' => 'Aloqabank',
        'a2a_supported' => false, 'is_active' => true,
    ]);

    BankBranch::create([
        'bank_id' => $bank->id, 'mfo' => '00401', 'name_uz' => 'Aloqabank HQ',
        'match_status' => BranchMatchStatus::Confirmed, 'is_active' => true,
    ]);

    $merchant = Merchant::create([
        'name' => 'Toshkent Davlat Universiteti',
        'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
        'mfo' => '00401', 'bank_account' => '29801000990248844444', 'stir' => '123456789',
    ]);

    $psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);

    $student = Student::withoutGlobalScopes()->create([
        'merchant_id' => $merchant->id, 'student_id_number' => 'STU-0002',
        'first_name' => 'Malika', 'last_name' => 'Yusupova',
    ]);

    foreach (range(1, $payments) as $i) {
        Transaction::withoutGlobalScopes()->create([
            'psp_id' => $psp->id,
            'merchant_id' => $merchant->id,
            'student_id' => $student->id,
            'partner_transaction_id' => 'PT-'.$i,
            'amount' => 1_000_000,
            'commission_amount' => 15_000,
            'net_amount' => 985_000,
            'status' => TransactionStatus::Completed,
            'paid_at' => now()->subDays($i),
        ]);
    }
}

it('traces a posting back to its tuition payment', function () {
    accountingFixtures();
    (new AccountingDemoSeeder)->run();

    $posting = BankTransfer::whereNotNull('transaction_id')->firstOrFail();
    $payments = $posting->sourcePayments();

    expect($payments)->toHaveCount(1)
        ->and($payments->first()->id)->toBe($posting->transaction_id)
        ->and($posting->sourceLabel())->toBe('TXN #'.$posting->transaction_id)
        ->and($payments->first()->student)->not->toBeNull();
});

it('resolves every payment behind a batched posting', function () {
    accountingFixtures();

    $merchant = Merchant::withoutGlobalScopes()->firstOrFail();
    $txns = Transaction::withoutGlobalScopes()->limit(3)->get();

    $payout = Payout::create([
        'merchant_id' => $merchant->id,
        'reference' => 'PO-TEST-1',
        'amount' => $txns->sum('net_amount'),
        'status' => 'pending',
    ]);

    foreach ($txns as $t) {
        PayoutItem::create([
            'payout_id' => $payout->id,
            'transaction_id' => $t->id,
            'net_amount' => $t->net_amount,
        ]);
    }

    $posting = BankTransfer::create([
        'payout_id' => $payout->id,
        'merchant_id' => $merchant->id,
        'reference' => 'EG-BATCH-1',
        'amount' => $payout->amount,
        'recipient_account' => '29801000990248844444',
        'recipient_mfo' => '00401',
        'recipient_name' => $merchant->name,
        'status' => BankTransferStatus::Confirmed,
    ]);

    expect($posting->sourcePayments())->toHaveCount(3)
        ->and($posting->sourceLabel())->toBe('PO-TEST-1 (3)');
});

it('says "manual" when a posting has no source payment', function () {
    accountingFixtures();
    $merchant = Merchant::withoutGlobalScopes()->firstOrFail();

    $posting = BankTransfer::create([
        'merchant_id' => $merchant->id,
        'reference' => 'EG-MANUAL-1',
        'amount' => 1000,
        'recipient_account' => '29801000990248844444',
        'recipient_mfo' => '00401',
        'recipient_name' => $merchant->name,
        'status' => BankTransferStatus::Pending,
    ]);

    expect($posting->sourceLabel())->toBe('manual')
        ->and($posting->sourcePayments())->toBeEmpty();
});

it('never allows a posting to be created, edited or deleted by hand', function () {
    // Money tables are append-only; corrections are new rows, never edits.
    expect(BankTransferResource::canCreate())->toBeFalse()
        ->and(BankTransferResource::canEdit(new BankTransfer))->toBeFalse()
        ->and(BankTransferResource::canDelete(new BankTransfer))->toBeFalse();
});

it('badges unreconciled postings for attention', function () {
    // Enough payments for the seeder's status spread to reach `unknown`.
    accountingFixtures(payments: 8);
    (new AccountingDemoSeeder)->run();

    $unknown = BankTransfer::where('status', BankTransferStatus::Unknown)->count();

    expect($unknown)->toBeGreaterThan(0)
        ->and(BankTransferResource::getNavigationBadge())->toBe((string) $unknown)
        ->and(BankTransferResource::getNavigationBadgeColor())->toBe('danger');
});

it('renders the postings register and the detail view', function () {
    accountingFixtures();
    (new AccountingDemoSeeder)->run();
    $posting = BankTransfer::whereNotNull('transaction_id')->firstOrFail();
    $student = $posting->transaction->student->fullName();

    actingAs(admin(), 'admin')
        ->get(BankTransferResource::getUrl('index'))
        ->assertOk();

    actingAs(AdminUser::first(), 'admin')
        ->get(BankTransferResource::getUrl('view', ['record' => $posting]))
        ->assertOk()
        // The traceability the screen promises must actually be on the page.
        ->assertSee($student)
        ->assertSee($posting->reference);
});

it('renders the bank drivers page', function () {
    accountingFixtures();
    (new AccountingDemoSeeder)->run();

    actingAs(admin(), 'admin')
        ->get(BankDrivers::getUrl())
        ->assertOk();
});

it('lists a bank as Ready only when flag, driver and account all line up', function () {
    accountingFixtures();
    (new AccountingDemoSeeder)->run();

    $bank = Bank::where('a2a_supported', true)->whereNotNull('a2a_driver')->firstOrFail();

    expect(SettlementAccount::where('bank_id', $bank->id)->where('is_active', true)->exists())
        ->toBeTrue()
        ->and($bank->canSendA2a())->toBeTrue();

    // Remove the account and the rail is no longer usable, flag notwithstanding.
    SettlementAccount::where('bank_id', $bank->id)->update(['is_active' => false]);

    expect($bank->fresh()->canSendA2a())->toBeFalse();
});
