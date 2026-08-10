<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\TransactionStatus;
use App\Livewire\Merchant\Payments;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaymentReceipt;
use App\Models\Psp;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * The receipt button in the merchant cabinet.
 *
 * The button hands out a link that needs no login, so the one thing that must
 * hold is that staff can only mint links for their own institution's payments.
 */
function makePayment(string $institution, string $email): array
{
    $merchant = Merchant::create([
        'name' => $institution,
        'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);

    $user = MerchantUser::create([
        'merchant_id' => $merchant->id, 'name' => 'Finance Officer',
        'email' => $email, 'password' => Hash::make('secret-password'),
        'is_active' => true, 'password_changed_at' => now(),
    ]);

    $psp = Psp::create([
        'name' => 'ClickPay '.$merchant->id, 'code' => 'clickpay'.$merchant->id,
        'status' => PspStatus::Active,
    ]);

    $student = Student::withoutGlobalScopes()->create([
        'merchant_id' => $merchant->id, 'student_id_number' => 'STU-'.$merchant->id,
        'first_name' => 'Malika', 'last_name' => 'Yusupova',
    ]);

    $txn = Transaction::withoutGlobalScopes()->create([
        'psp_id' => $psp->id, 'merchant_id' => $merchant->id, 'student_id' => $student->id,
        'partner_transaction_id' => 'PT-'.$merchant->id, 'amount' => 600_000_000,
        'commission_amount' => 9_000_000, 'net_amount' => 591_000_000,
        'status' => TransactionStatus::Completed, 'paid_at' => now(),
    ]);

    return [$user, $txn];
}

it('issues a receipt and shows its link when the button is pressed', function () {
    [$user, $txn] = makePayment('Toshkent Davlat Universiteti', 'finance@tdu.uz');

    Livewire::actingAs($user, 'merchant')
        ->test(Payments::class)
        ->assertSet('receiptUrl', null)
        ->call('receipt', $txn->id)
        ->assertSet('receiptUrl', fn ($url) => str_contains((string) $url, '/chek/'))
        ->assertSet('receiptNumber', fn ($n) => str_starts_with((string) $n, 'EG-'));

    expect(PaymentReceipt::where('transaction_id', $txn->id)->exists())->toBeTrue();
});

it('reuses the same link on a second press', function () {
    [$user, $txn] = makePayment('Toshkent Davlat Universiteti', 'finance@tdu.uz');

    $component = Livewire::actingAs($user, 'merchant')->test(Payments::class);

    $component->call('receipt', $txn->id);
    $first = $component->get('receiptUrl');

    $component->call('closeReceipt')->call('receipt', $txn->id);

    // A second link to the same payment would mean two live addresses to
    // revoke if one ever leaked.
    expect($component->get('receiptUrl'))->toBe($first)
        ->and(PaymentReceipt::count())->toBe(1);
});

it('will not mint a link for another institution\'s payment', function () {
    [$ours] = makePayment('Toshkent Davlat Universiteti', 'finance@tdu.uz');
    [, $theirs] = makePayment('Samarqand Davlat Universiteti', 'finance@samdu.uz');

    // Guessing an id must not become a way to read a rival's student names.
    // The tenant scope hides the row entirely, so the lookup misses — over
    // HTTP the handler turns that into a 404.
    expect(fn () => Livewire::actingAs($ours, 'merchant')
        ->test(Payments::class)
        ->call('receipt', $theirs->id)
    )->toThrow(ModelNotFoundException::class);

    expect(PaymentReceipt::count())->toBe(0);
});

it('lists only our own payments', function () {
    [$ours, $ourTxn] = makePayment('Toshkent Davlat Universiteti', 'finance@tdu.uz');
    [, $theirTxn] = makePayment('Samarqand Davlat Universiteti', 'finance@samdu.uz');

    Livewire::actingAs($ours, 'merchant')
        ->test(Payments::class)
        ->assertSee($ourTxn->partner_transaction_id)
        ->assertDontSee($theirTxn->partner_transaction_id);
});

it('closes the link dialog', function () {
    [$user, $txn] = makePayment('Toshkent Davlat Universiteti', 'finance@tdu.uz');

    Livewire::actingAs($user, 'merchant')
        ->test(Payments::class)
        ->call('receipt', $txn->id)
        ->call('closeReceipt')
        ->assertSet('receiptUrl', null)
        ->assertSet('receiptNumber', null);
});

it('reaches the payments page as a signed-in officer', function () {
    [$user, $txn] = makePayment('Toshkent Davlat Universiteti', 'finance@tdu.uz');

    PaymentReceipt::forTransaction($txn);

    actingAs($user, 'merchant')
        ->get('/merchant/payments')
        ->assertOk()
        ->assertSee(__('receipt.open'));
});
