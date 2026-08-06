<?php

declare(strict_types=1);

use App\Enums\BranchMatchStatus;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\Merchant;
use App\Models\Psp;
use App\Models\Student;

beforeEach(function () {
    $bank = Bank::create(['code' => '00401', 'slug' => 'aloqabank', 'name_uz' => 'Aloqabank']);
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
