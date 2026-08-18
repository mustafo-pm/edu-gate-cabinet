<?php

declare(strict_types=1);

use App\Enums\MerchantBankAccountStatus;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\Merchant;
use App\Models\MerchantBankAccount;

/**
 * An institution's bank accounts.
 *
 * This is where money leaves the platform, so the rules are stricter than a
 * profile field deserves: an account cannot receive until EduGate has approved
 * it, exactly one is primary, and retiring one never rewrites where past
 * settlements went.
 */
beforeEach(function () {
    $this->merchant = Merchant::create([
        'name' => 'Webster University', 'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
    ]);
});

function account(Merchant $m, array $attrs = []): MerchantBankAccount
{
    return MerchantBankAccount::withoutGlobalScopes()->create(array_merge([
        'merchant_id' => $m->id,
        'bank_name' => 'Davr Bank', 'mfo' => '01041',
        'account_number' => '20208000900000000001',
    ], $attrs));
}

it('creates an account that cannot receive money yet', function () {
    $a = account($this->merchant);

    // Proposed by the institution, not yet checked by us.
    expect($a->status)->toBe(MerchantBankAccountStatus::Pending)
        ->and($a->canReceive())->toBeFalse()
        ->and($this->merchant->primaryBankAccount())->toBeNull();
});

it('refuses to make an unapproved account primary', function () {
    $a = account($this->merchant);

    // The approval step is the only thing between a stolen cabinet password
    // and a redirected term's tuition.
    expect(fn () => $a->makePrimary())->toThrow(RuntimeException::class);
});

it('settles to the approved account once there is one', function () {
    $a = account($this->merchant, ['status' => MerchantBankAccountStatus::Active]);

    expect($this->merchant->primaryBankAccount()?->id)->toBe($a->id);
});

it('keeps exactly one primary account', function () {
    $davr = account($this->merchant, ['status' => MerchantBankAccountStatus::Active]);
    $ipak = account($this->merchant, [
        'status' => MerchantBankAccountStatus::Active,
        'bank_name' => 'Ipak Yuli', 'mfo' => '00873',
        'account_number' => '20208000900000000002',
    ]);

    $davr->makePrimary();
    $ipak->makePrimary();

    // The university moved banks; both accounts stay on file and live, but
    // money only ever goes to one of them.
    expect($ipak->fresh()->is_primary)->toBeTrue()
        ->and($davr->fresh()->is_primary)->toBeFalse()
        ->and($this->merchant->primaryBankAccount()->id)->toBe($ipak->id);
});

it('falls back to the single approved account when none is marked primary', function () {
    // An institution with one account should never have to press a button it
    // was never told about.
    $a = account($this->merchant, ['status' => MerchantBankAccountStatus::Active]);

    expect($a->is_primary)->toBeFalse()
        ->and($this->merchant->primaryBankAccount()->id)->toBe($a->id);
});

it('ignores rejected and archived accounts', function () {
    account($this->merchant, ['status' => MerchantBankAccountStatus::Rejected]);
    account($this->merchant, [
        'status' => MerchantBankAccountStatus::Archived,
        'account_number' => '20208000900000000009',
    ]);

    expect($this->merchant->primaryBankAccount())->toBeNull();
});

it('never shows one institution the accounts of another', function () {
    $other = Merchant::create([
        'name' => 'Rival', 'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);
    account($other, ['status' => MerchantBankAccountStatus::Active]);

    expect($this->merchant->bankAccounts()->count())->toBe(0);
});

it('masks the account number for display', function () {
    $a = account($this->merchant);

    // Enough to tell two accounts apart on screen, not enough to copy down.
    expect($a->maskedNumber())->toEndWith('0001')
        ->and($a->maskedNumber())->not->toContain('20208000900');
});
