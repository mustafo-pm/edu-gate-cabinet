<?php

declare(strict_types=1);

use App\Enums\MerchantBankAccountStatus;
use App\Enums\MerchantContactKind;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Merchant\BankAccounts;
use App\Livewire\Merchant\Profile;
use App\Models\Merchant;
use App\Models\MerchantBankAccount;
use App\Models\MerchantContact;
use App\Models\MerchantUser;
use App\Models\PaymentReceipt;
use App\Models\Psp;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * The institution profile and its bank accounts.
 *
 * Two different risk levels sharing one cabinet: a name or a logo is the
 * institution's to change freely, a bank account decides where money lands and
 * is not.
 */
function institution(string $name = 'Webster University', string $email = 'finance@webster.uz'): MerchantUser
{
    $merchant = Merchant::create([
        'name' => $name, 'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);

    return MerchantUser::create([
        'merchant_id' => $merchant->id, 'name' => 'Finance Officer', 'email' => $email,
        'password' => Hash::make('secret-password'), 'is_active' => true,
        'password_changed_at' => now(),
    ]);
}

it('saves names in three languages', function () {
    $user = institution();

    Livewire::actingAs($user, 'merchant')
        ->test(Profile::class)
        ->set('name_uz', 'Vebster universiteti')
        ->set('name_ru', 'Университет Вебстер')
        ->set('name_en', 'Webster University')
        ->call('save')
        ->assertHasNoErrors();

    $m = $user->merchant->fresh();

    expect($m->displayName('ru'))->toBe('Университет Вебстер')
        ->and($m->displayName('en'))->toBe('Webster University')
        ->and($m->displayName('uz'))->toBe('Vebster universiteti');
});

it('falls back to a name it has rather than showing a blank', function () {
    $user = institution();
    $user->merchant->update(['name_uz' => 'Vebster', 'name_ru' => null, 'name_en' => null]);

    // A half-filled profile must never put an unnamed institution on a receipt.
    expect($user->merchant->fresh()->displayName('ru'))->toBe('Vebster');
});

it('rejects a website that is not a url', function () {
    $user = institution();

    Livewire::actingAs($user, 'merchant')
        ->test(Profile::class)
        ->set('website_url', 'not a website')
        ->call('save')
        ->assertHasErrors('website_url');
});

it('keeps the institution off the receipt until it opts in', function () {
    $user = institution();
    $user->merchant->update([
        'website_url' => 'https://webster.uz',
        'logo_light_path' => 'merchants/1/logo.png',
    ]);

    $psp = Psp::create([
        'name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active,
    ]);
    $student = Student::withoutGlobalScopes()->create([
        'merchant_id' => $user->merchant_id, 'student_id_number' => 'STU-1',
        'first_name' => 'A', 'last_name' => 'B',
    ]);
    $txn = Transaction::withoutGlobalScopes()->create([
        'psp_id' => $psp->id, 'merchant_id' => $user->merchant_id, 'student_id' => $student->id,
        'partner_transaction_id' => 'PT-1', 'amount' => 100, 'commission_amount' => 1,
        'net_amount' => 99, 'status' => TransactionStatus::Completed, 'paid_at' => now(),
    ]);

    $receipt = PaymentReceipt::forTransaction($txn);

    // Uploaded, but not published: putting a mark on a document a stranger
    // holds is a decision, not a side effect of filling in a profile.
    expect($receipt->institutionLogoUrl())->toBeNull()
        ->and($receipt->institutionWebsite())->toBeNull();

    $user->merchant->update(['show_on_receipt' => true]);
    $receipt = $receipt->fresh()->load('transaction.merchant');

    expect($receipt->institutionLogoUrl())->toContain('logo.png')
        ->and($receipt->institutionWebsite())->toBe('https://webster.uz');
});

it('stores contacts for several departments', function () {
    $user = institution();

    Livewire::actingAs($user, 'merchant')
        ->test(Profile::class)
        ->call('addContact')
        ->set('contacts.0.kind', MerchantContactKind::Accounting->value)
        ->set('contacts.0.phone', '+998 71 200 00 01')
        ->set('contacts.0.is_public', true)
        ->call('addContact')
        ->set('contacts.1.kind', MerchantContactKind::StudentAffairs->value)
        ->set('contacts.1.phone', '+998 71 200 00 02')
        ->call('saveContacts')
        ->assertHasNoErrors();

    $contacts = MerchantContact::withoutGlobalScopes()->where('merchant_id', $user->merchant_id)->get();

    expect($contacts)->toHaveCount(2)
        // A payer gets the accounting desk, not the registrar's direct line.
        ->and($contacts->where('is_public', true))->toHaveCount(1);
});

it('will not touch another institution contact', function () {
    $ours = institution();
    $theirs = institution('Rival', 'finance@rival.uz');

    $stranger = MerchantContact::withoutGlobalScopes()->create([
        'merchant_id' => $theirs->merchant_id,
        'kind' => MerchantContactKind::Accounting, 'phone' => '+998 90 000 00 00',
    ]);

    $component = Livewire::actingAs($ours, 'merchant')->test(Profile::class)->call('addContact');
    $component->set('contacts.0.id', $stranger->id)->set('contacts.0.phone', 'hijacked');

    expect(fn () => $component->call('saveContacts'))
        ->toThrow(ModelNotFoundException::class);

    expect($stranger->fresh()->phone)->toBe('+998 90 000 00 00');
});

// ── Bank accounts ───────────────────────────────────────────────────────

it('submits a bank account for approval rather than activating it', function () {
    $user = institution();

    Livewire::actingAs($user, 'merchant')
        ->test(BankAccounts::class)
        ->set('bank_name', 'Ipak Yuli')
        ->set('mfo', '00873')
        ->set('account_number', '20208000900000000222')
        ->call('add')
        ->assertHasNoErrors();

    $account = MerchantBankAccount::withoutGlobalScopes()->first();

    expect($account->status)->toBe(MerchantBankAccountStatus::Pending)
        ->and($user->merchant->primaryBankAccount())->toBeNull();
});

it('refuses a malformed account number or MFO', function () {
    $user = institution();

    Livewire::actingAs($user, 'merchant')
        ->test(BankAccounts::class)
        ->set('bank_name', 'Ipak Yuli')
        ->set('mfo', '873')
        ->set('account_number', '123')
        ->call('add')
        ->assertHasErrors(['mfo', 'account_number']);
});

it('refuses the same account twice', function () {
    $user = institution();

    $add = fn () => Livewire::actingAs($user, 'merchant')
        ->test(BankAccounts::class)
        ->set('bank_name', 'Ipak Yuli')
        ->set('mfo', '00873')
        ->set('account_number', '20208000900000000222')
        ->call('add');

    $add();
    $add()->assertHasErrors('account_number');

    expect(MerchantBankAccount::withoutGlobalScopes()->count())->toBe(1);
});

it('will not retire the account money is going to', function () {
    $user = institution();

    $account = MerchantBankAccount::withoutGlobalScopes()->create([
        'merchant_id' => $user->merchant_id, 'bank_name' => 'Davr', 'mfo' => '01041',
        'account_number' => '20208000900000000111',
        'status' => MerchantBankAccountStatus::Active,
    ]);
    $account->makePrimary();

    Livewire::actingAs($user, 'merchant')
        ->test(BankAccounts::class)
        ->call('archive', $account->id);

    // Otherwise the institution silently stops being paid.
    expect($account->fresh()->status)->toBe(MerchantBankAccountStatus::Active);
});

it('never lists another institution accounts', function () {
    $ours = institution();
    $theirs = institution('Rival', 'finance@rival.uz');

    MerchantBankAccount::withoutGlobalScopes()->create([
        'merchant_id' => $theirs->merchant_id, 'bank_name' => 'Secret Bank',
        'mfo' => '00001', 'account_number' => '20208000900000009999',
        'status' => MerchantBankAccountStatus::Active,
    ]);

    Livewire::actingAs($ours, 'merchant')
        ->test(BankAccounts::class)
        ->assertDontSee('Secret Bank');
});

it('opens both cabinet pages', function () {
    $user = institution();

    Pest\Laravel\actingAs($user, 'merchant')->get('/merchant/profile')->assertOk();
    Pest\Laravel\actingAs($user, 'merchant')->get('/merchant/bank-accounts')->assertOk();
});
