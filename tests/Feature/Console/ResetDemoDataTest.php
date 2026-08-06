<?php

declare(strict_types=1);

use App\Enums\BranchMatchStatus;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\Merchant;
use App\Models\Psp;
use App\Models\SettlementAccount;
use App\Models\Student;

beforeEach(function () {
    $bank = Bank::create([
        'code' => '00401', 'slug' => 'aloqabank', 'name_uz' => 'Aloqabank',
        'a2a_supported' => true, 'a2a_driver' => 'aloqabank', 'is_active' => true,
    ]);
    BankBranch::create([
        'bank_id' => $bank->id, 'mfo' => '00401', 'name_uz' => 'HQ',
        'match_status' => BranchMatchStatus::Confirmed, 'is_active' => true,
    ]);
});

it('seeds exactly one institution, three students and one psp', function () {
    $this->artisan('demo:reset --no-interaction')->assertSuccessful();

    expect(Merchant::withoutGlobalScopes()->count())->toBe(1)
        ->and(Student::withoutGlobalScopes()->count())->toBe(3)
        ->and(Psp::withoutGlobalScopes()->count())->toBe(1);
});

it('routes the demo institution to a confirmed branch', function () {
    $this->artisan('demo:reset --no-interaction')->assertSuccessful();

    $merchant = Merchant::withoutGlobalScopes()->first();
    $branch = BankBranch::where('mfo', $merchant->mfo)->first();

    // An unconfirmed MFO would leave every posting blocked on arrival.
    expect($branch->match_status)->toBe(BranchMatchStatus::Confirmed);
});

it('refuses to delete financial records in production without --force', function () {
    app()->detectEnvironment(fn () => 'production');

    Merchant::create([
        'name' => 'Real institution',
        'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
    ]);

    $this->artisan('demo:reset --no-interaction')->assertFailed();

    expect(Merchant::withoutGlobalScopes()->count())->toBe(1);
});

it('creates a settlement account when none exists, pointed at the simulator', function () {
    config(['services.aloqabank.base_url' => 'http://localhost/sim/aloqabank/api/v2']);

    $this->artisan('demo:reset --no-interaction')->assertSuccessful();

    $account = SettlementAccount::first();

    expect($account)->not->toBeNull()
        ->and($account->driver)->toBe('aloqabank')
        ->and($account->is_active)->toBeTrue()
        ->and($account->label)->toContain('DEMO');
});

it('refuses to invent settlement details when the driver points at a real bank', function () {
    config(['services.aloqabank.base_url' => 'https://api.aloqabank.uz/api/v2']);

    // Inventing EduGate's own bank requisites is not something a seeder may do.
    $this->artisan('demo:reset --no-interaction')->assertFailed();
});

it('routes the demo institution to a bank we hold a driver for', function () {
    // A second bank with no driver, listed first — the naive "first branch"
    // pick landed here and produced a demo that could never settle.
    $other = Bank::create(['code' => '00099', 'slug' => 'other', 'name_uz' => 'Other Bank']);
    BankBranch::create([
        'bank_id' => $other->id, 'mfo' => '00099', 'name_uz' => 'Other HQ',
        'match_status' => BranchMatchStatus::Unmapped, 'is_active' => true,
    ]);

    config(['services.aloqabank.base_url' => 'http://localhost/sim/aloqabank/api/v2']);
    $this->artisan('demo:reset --no-interaction')->assertSuccessful();

    $merchant = Merchant::withoutGlobalScopes()->first();
    expect($merchant->mfo)->toBe('00401');
});
