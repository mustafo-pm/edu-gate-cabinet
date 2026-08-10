<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\TransactionStatus;
use App\Models\Merchant;
use App\Models\PaymentReceipt;
use App\Models\Psp;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Which language the receipt opens in.
 *
 * Nobody chooses to visit this page — they scan a code — so it has to be in
 * their language before they touch anything.
 */
beforeEach(function () {
    RateLimiter::clear('receipt:look:127.0.0.1');
    RateLimiter::clear('receipt:miss:127.0.0.1');

    $merchant = Merchant::create([
        'name' => 'Toshkent Davlat Universiteti',
        'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);

    $psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);

    $student = Student::withoutGlobalScopes()->create([
        'merchant_id' => $merchant->id, 'student_id_number' => 'STU-0001',
        'first_name' => 'Malika', 'last_name' => 'Yusupova',
    ]);

    $txn = Transaction::withoutGlobalScopes()->create([
        'psp_id' => $psp->id, 'merchant_id' => $merchant->id, 'student_id' => $student->id,
        'partner_transaction_id' => 'PT-1', 'amount' => 600_000_000,
        'commission_amount' => 9_000_000, 'net_amount' => 591_000_000,
        'status' => TransactionStatus::Completed, 'paid_at' => now(),
    ]);

    $this->receipt = PaymentReceipt::forTransaction($txn);
    $this->url = '/chek/'.$this->receipt->code;
});

it('opens in the language the device asks for', function () {
    $this->get($this->url, ['Accept-Language' => 'ru-RU,ru;q=0.9'])
        ->assertOk()
        ->assertSee(__('receipt.confirmed', [], 'ru'));

    $this->get($this->url, ['Accept-Language' => 'en-GB,en;q=0.9'])
        ->assertOk()
        ->assertSee(__('receipt.confirmed', [], 'en'));
});

it('falls back to Uzbek for a language we do not publish', function () {
    // A German phone is not a reason to show a receipt in English; Uzbek is the
    // language of the country this document belongs to.
    $this->get($this->url, ['Accept-Language' => 'de-DE,de;q=0.9'])
        ->assertOk()
        ->assertSee(__('receipt.confirmed', [], 'uz'));
});

it('falls back to Uzbek when the device says nothing', function () {
    // The header has to be blanked explicitly: Laravel's test client fakes an
    // "en-us" preference on every request, so plain get() would test the
    // framework's default rather than ours and quietly pass on English.
    $this->get($this->url, ['Accept-Language' => ''])
        ->assertOk()
        ->assertSee(__('receipt.confirmed', [], 'uz'));
});

it('lets an explicit choice beat the device', function () {
    // The switcher has to work, and a link shared in one language must open in
    // that language for whoever receives it.
    $this->get($this->url.'?lang=ru', ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertOk()
        ->assertSee(__('receipt.confirmed', [], 'ru'))
        ->assertDontSee(__('receipt.confirmed', [], 'en'));
});

it('ignores a language we do not publish in the query', function () {
    $this->get($this->url.'?lang=de', ['Accept-Language' => 'ru-RU'])
        ->assertOk()
        // Not German, and not silently English either — the device preference
        // still applies once the bad value is discarded.
        ->assertSee(__('receipt.confirmed', [], 'ru'));
});

it('tells caches that the page varies by language', function () {
    // Without this a shared cache could hand the Russian copy to the next
    // visitor, whatever their phone asked for.
    $this->get($this->url)->assertHeader('Vary', 'Accept-Language');
});

it('offers the other languages as links', function () {
    $page = $this->get($this->url)->assertOk();

    foreach (config('receipt.locales') as $locale) {
        $page->assertSee($this->receipt->code.'?lang='.$locale, escape: false);
    }
});

it('carries the chosen language into the PDF', function () {
    $response = $this->get($this->url.'/pdf?lang=ru');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('application/pdf');
});
