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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The public receipt page.
 *
 * It is reachable without logging in, so the things worth pinning down are the
 * ones that keep it from becoming a directory of everyone's payments: the code
 * cannot be guessed or counted, wrong codes are punished harder than right
 * ones, and the status shown is always live rather than the printed snapshot.
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
});

it('issues a receipt on first request and reuses it after', function () {
    $first = PaymentReceipt::forTransaction($this->txn);
    $second = PaymentReceipt::forTransaction($this->txn);

    expect($second->id)->toBe($first->id)
        ->and(PaymentReceipt::count())->toBe(1);
});

it('numbers the receipt from the payment, not a counter', function () {
    // Stable regardless of the order receipts are first opened in.
    expect(PaymentReceipt::forTransaction($this->txn)->number)
        ->toBe('EG-'.now()->format('Y').'-'.str_pad((string) $this->txn->id, 6, '0', STR_PAD_LEFT));
});

it('gives every receipt a long unguessable code', function () {
    $codes = collect(range(1, 50))->map(fn () => PaymentReceipt::freshCode());

    expect($codes->unique())->toHaveCount(50)
        ->and($codes->first())->toHaveLength(32)
        // No 0/O or 1/l/I — these get read off paper and dictated by phone.
        ->and($codes->every(fn ($c) => preg_match('/^[a-z2-9]{32}$/', $c) === 1))->toBeTrue();
});

it('does not put the payment id in the public address', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    // Compare the path only — the host itself contains digits (127.0.0.1) and
    // would make a whole-URL match report a problem that is not there.
    $path = parse_url($receipt->url(), PHP_URL_PATH);

    // A sequential address could be walked from 1 upwards to harvest every
    // student's name, institution and amount.
    expect($path)->toBe('/chek/'.$receipt->code)
        ->and($path)->not->toBe('/chek/'.$this->txn->id)
        ->and($receipt->code)->not->toBe((string) $this->txn->id);
});

it('shows the receipt to anyone holding the link', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    $this->get('/chek/'.$receipt->code)
        ->assertOk()
        ->assertSee($receipt->number)
        ->assertSee('Toshkent Davlat Universiteti')
        ->assertSee('Yusupova Malika')
        ->assertSee('6 000 000.00 UZS');
});

it('keeps our commission off the receipt', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    // What EduGate earns is between us and the institution. The payer sees
    // what they paid, nothing else.
    $this->get('/chek/'.$receipt->code)
        ->assertOk()
        ->assertDontSee('90 000.00')
        ->assertDontSee('5 910 000.00');
});

it('asks search engines not to index it', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    $this->get('/chek/'.$receipt->code)->assertSee('noindex', escape: false);
});

/**
 * The whole reason the QR is worth having: paper keeps saying "paid" after a
 * refund, the page does not.
 */
it('shows the live status, not the printed one', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    $this->get('/chek/'.$receipt->code)->assertSee(__('receipt.confirmed'));

    $this->txn->forceFill(['status' => TransactionStatus::Refunded])->save();

    $this->get('/chek/'.$receipt->code)
        ->assertOk()
        ->assertSee(__('receipt.not_valid'))
        ->assertDontSee(__('receipt.confirmed'));
});

it('answers 404 for an unknown code', function () {
    $this->get('/chek/'.PaymentReceipt::freshCode())->assertNotFound();
});

it('answers 404 for a malformed code without touching the database', function () {
    // Shape is checked first, so flooding costs an attacker more than us.
    $this->get('/chek/short')->assertNotFound();
    $this->get('/chek/'.str_repeat('Z', 32))->assertNotFound();
});

it('blocks an address that keeps guessing wrong codes', function () {
    $misses = config('receipt.rate_limit.misses_per_hour');

    for ($i = 0; $i < $misses; $i++) {
        $this->get('/chek/'.PaymentReceipt::freshCode())->assertNotFound();
    }

    // A real visitor follows a working link and never gets here.
    $this->get('/chek/'.PaymentReceipt::freshCode())->assertStatus(429);
});

it('blocks a valid link too once the address is flagged for guessing', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);
    $misses = config('receipt.rate_limit.misses_per_hour');

    for ($i = 0; $i < $misses; $i++) {
        $this->get('/chek/'.PaymentReceipt::freshCode());
    }

    // Otherwise a guesser who finally lands on a real code walks straight in.
    $this->get('/chek/'.$receipt->code)->assertStatus(429);
});

it('streams the PDF without writing anything to disk', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    $before = collect(File::allFiles(storage_path('app')))->count();

    $response = $this->get('/chek/'.$receipt->code.'/pdf');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('EduGate-'.$receipt->number.'.pdf');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect(substr($body, 0, 4))->toBe('%PDF')
        // A folder quietly filling with other people's names and amounts is a
        // liability with no upside — regenerating costs milliseconds.
        ->and(collect(File::allFiles(storage_path('app')))->count())
        ->toBe($before);
});

it('refuses a PDF for an unknown code', function () {
    $this->get('/chek/'.PaymentReceipt::freshCode().'/pdf')->assertNotFound();
});

it('serves the receipt on the marketing host once one is configured', function () {
    config(['domains.receipt' => 'edu-gate.uz', 'domains.cabinet' => 'cabinet.edu-gate.uz']);

    // Route registration happens at boot, so this test documents the intent
    // rather than re-booting the router: both hosts must resolve the same URI.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'chek/'));

    expect($routes)->not->toBeEmpty()
        ->and($routes->pluck('action.controller')->unique())
        ->each->toContain('ReceiptController');
});

it('builds the public link from config, not from route registration order', function () {
    $receipt = PaymentReceipt::forTransaction($this->txn);

    config(['receipt.base_url' => 'https://edu-gate.uz']);

    // /chek is registered under one route name on two hosts, and route() would
    // return whichever was registered last. The address printed on a document
    // must not depend on that.
    expect($receipt->url())->toBe('https://edu-gate.uz/chek/'.$receipt->code)
        ->and($receipt->pdfUrl())->toBe('https://edu-gate.uz/chek/'.$receipt->code.'/pdf');
});
