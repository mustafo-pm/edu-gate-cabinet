<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PartnerCategory;
use App\Enums\PspStatus;
use App\Enums\TransactionStatus;
use App\Models\Merchant;
use App\Models\Partner;
use App\Models\PaymentReceipt;
use App\Models\Psp;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Support\Facades\RateLimiter;

/**
 * GET /api/public/receipt/{code}
 *
 * One unauthenticated endpoint the marketing site calls to render a receipt.
 * The link is the credential, so the tests here are mostly about what an
 * uninvited caller can and cannot get out of it.
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

    $this->txn = Transaction::withoutGlobalScopes()->create([
        'psp_id' => $psp->id, 'merchant_id' => $merchant->id, 'student_id' => $student->id,
        'partner_transaction_id' => 'PT-1', 'amount' => 600_000_000,
        'commission_amount' => 9_000_000, 'net_amount' => 591_000_000,
        'status' => TransactionStatus::Completed, 'paid_at' => now(),
    ]);

    $this->receipt = PaymentReceipt::forTransaction($this->txn);
});

it('returns the payment for a valid code', function () {
    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.number', $this->receipt->number)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.status.value', 'completed')
        ->assertJsonPath('data.institution', 'Toshkent Davlat Universiteti')
        ->assertJsonPath('data.student.name', 'Yusupova Malika')
        ->assertJsonPath('data.student.number', 'STU-0001')
        ->assertJsonPath('data.amount', 600_000_000)
        ->assertJsonPath('data.currency', 'UZS')
        ->assertJsonPath('data.paid_via.name', 'ClickPay');
});

it('names the status in every language the website offers', function () {
    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertJsonPath('data.status.label.uz', 'Yakunlangan')
        ->assertJsonPath('data.status.label.ru', 'Завершено')
        ->assertJsonPath('data.status.label.en', 'Completed');
});

it('captions every field in every language', function () {
    $labels = $this->getJson('/api/public/receipt/'.$this->receipt->code)->json('data.labels');

    // The page is static and has no backend — if a caption were missing it
    // would have to keep its own copy of our wording, which would drift.
    foreach (['status', 'number', 'institution', 'student', 'amount', 'paid_via', 'paid_at', 'checked_at'] as $field) {
        expect($labels)->toHaveKey($field)
            ->and($labels[$field])->toHaveKeys(config('receipt.locales'));

        foreach ($labels[$field] as $locale => $text) {
            expect($text)->not->toBeEmpty("{$field} has no {$locale} caption")
                // A missing key returns the key itself, which would render as
                // "receipt.amount" on a document handed to a stranger.
                ->and($text)->not->toContain('receipt.');
        }
    }

    expect($labels['amount']['uz'])->toBe('Summa')
        ->and($labels['amount']['ru'])->toBe('Сумма');
});

it('carries the brand colour and an icon for the status', function () {
    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertJsonPath('data.status.color.token', 'success')
        ->assertJsonPath('data.status.color.text', '#059669')
        ->assertJsonPath('data.status.color.background', '#ECFDF5')
        // Colour must never carry the meaning alone.
        ->assertJsonPath('data.status.icon', 'check-circle');
});

it('turns red with a different icon once the payment is refunded', function () {
    $this->txn->forceFill(['status' => TransactionStatus::Refunded])->save();

    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertJsonPath('data.status.color.text', '#7C3AED')
        ->assertJsonPath('data.status.icon', 'arrow-counter-clockwise')
        ->assertJsonPath('data.status.label.uz', __('cabinet.status.refunded', [], 'uz'));
});

it('reports amounts in tiyin alongside a formatted string', function () {
    // The integer is the number to compute with; the string only exists so a
    // static page does not have to reimplement our formatting.
    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertJsonPath('data.amount', 600_000_000)
        ->assertJsonPath('data.amount_formatted', '6 000 000.00 UZS');
});

it('keeps internal and financial detail out of the response', function () {
    $body = $this->getJson('/api/public/receipt/'.$this->receipt->code)->json('data');

    // Our commission is between us and the institution. Ids would let a caller
    // correlate receipts or walk the table.
    expect($body)->not->toHaveKeys([
        'id', 'transaction_id', 'merchant_id', 'psp_id', 'student_id',
        'commission_amount', 'net_amount',
    ]);
});

it('answers live rather than from the printed snapshot', function () {
    $this->txn->forceFill(['status' => TransactionStatus::Refunded])->save();

    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertOk()
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.status.value', 'refunded');
});

it('shows a provider logo only once it is published on the partner wall', function () {
    $psp = Psp::first();

    $partner = Partner::create([
        'slug' => 'clickpay', 'name_uz' => 'ClickPay', 'category' => PartnerCategory::PaymentProvider,
        'logo_path' => 'partners/clickpay.svg',
        'source_type' => Psp::class, 'source_id' => $psp->id,
        'is_published' => false, 'sort_order' => 0,
    ]);

    // Being a PSP is a commercial fact; having your mark on a document a
    // stranger will hold is somebody's decision, and it has not been made yet.
    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertJsonPath('data.paid_via.logo_url', null);

    $partner->update(['is_published' => true]);

    $logo = $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->json('data.paid_via.logo_url');

    expect($logo)->toContain('partners/clickpay.svg')
        // The website is on another host, so a relative path would not resolve.
        ->and($logo)->toStartWith('http');
});

it('forbids caching so a refund is never served stale', function () {
    $this->getJson('/api/public/receipt/'.$this->receipt->code)
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('answers 404 for an unknown code', function () {
    $this->getJson('/api/public/receipt/'.PaymentReceipt::freshCode())
        ->assertNotFound()
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('error.code', 'not_found');
});

it('rejects a malformed code at the route, before any query runs', function () {
    // Anything the wrong shape is not a receipt code, so it never becomes a
    // database round trip.
    $this->getJson('/api/public/receipt/short')->assertNotFound();
    $this->getJson('/api/public/receipt/'.str_repeat('A', 32))->assertNotFound();
    $this->getJson('/api/public/receipt/'.$this->txn->id)->assertNotFound();
});

it('throttles a caller that keeps guessing codes', function () {
    $misses = config('receipt.rate_limit.misses_per_hour');

    for ($i = 0; $i < $misses; $i++) {
        $this->getJson('/api/public/receipt/'.PaymentReceipt::freshCode())->assertNotFound();
    }

    $this->getJson('/api/public/receipt/'.PaymentReceipt::freshCode())
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'too_many_requests');

    // And the real link stops working for that caller too, so landing on a
    // valid code by luck buys nothing.
    $this->getJson('/api/public/receipt/'.$this->receipt->code)->assertStatus(429);
});

it('shares one throttle with the HTML page', function () {
    $misses = config('receipt.rate_limit.misses_per_hour');

    // Guessing against the JSON endpoint...
    for ($i = 0; $i < $misses; $i++) {
        $this->getJson('/api/public/receipt/'.PaymentReceipt::freshCode());
    }

    // ...must not leave the page as an unguarded second door.
    $this->get('/chek/'.$this->receipt->code)->assertStatus(429);
});

it('is reachable from the marketing site origin', function () {
    $this->getJson('/api/public/receipt/'.$this->receipt->code, [
        'Origin' => 'https://edu-gate.uz',
    ])->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://edu-gate.uz');
});
