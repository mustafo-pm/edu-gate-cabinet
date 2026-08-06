<?php

declare(strict_types=1);

use App\Simulators\Aloqabank\Models\SimPartner;
use App\Simulators\Aloqabank\Models\SimPayment;
use App\Simulators\Aloqabank\Models\SimService;
use App\Simulators\Aloqabank\Support\ErrorCode;
use Database\Seeders\AloqabankSimulatorSeeder;

use function Pest\Laravel\withHeaders;

/**
 * The simulator only earns its place if it reproduces the bank's awkward parts:
 * asynchronous settlement, a body that says "error" over HTTP 200, and failure
 * modes we would otherwise never exercise. These tests pin exactly that.
 */
const BASE = '/sim/aloqabank/api/v2';

/** Correct HTTP Basic header for the seeded partner. */
function basic(string $user = 'rpay', string $pass = 'p@ssw0rd'): array
{
    return ['Authorization' => 'Basic '.base64_encode("{$user}:{$pass}")];
}

function paymentBody(array $overrides = []): array
{
    return array_merge([
        'orderId' => 'ORD-'.uniqid(),
        'amount' => '500000',
        'comissionAmount' => '0',
        'purpose' => 'Tuition payment',
        'serviceId' => '33',
        'receiverName' => 'Toshkent Davlat Universiteti',
        'mfoReceiver' => '00401',
        'receiverAccount' => '29801000990248844444',
        'innReceiver' => '123456789',
    ], $overrides);
}

beforeEach(function () {
    (new AloqabankSimulatorSeeder)->run();
});

it('rejects bad credentials with 1004 inside a 200 body', function () {
    $r = withHeaders(basic('rpay', 'wrong'))->getJson(BASE.'/services');

    // The bank signals failure in the body, not the status line — a client that
    // only checks the HTTP code would treat this as success.
    $r->assertOk();
    expect($r->json('status'))->toBe('error')
        ->and($r->json('code'))->toBe(ErrorCode::USER_NOT_FOUND);
});

it('lists the partner services', function () {
    $r = withHeaders(basic())->getJson(BASE.'/services');

    $r->assertOk();
    expect($r->json('code'))->toBe(0)
        ->and($r->json('data'))->toHaveCount(3)
        ->and(collect($r->json('data'))->pluck('type'))
        ->toContain('WORKING_WITH_CARD', 'CARD_IS_OPTIONAL');
});

it('accepts a payment as "Введен", never as settled', function () {
    $r = withHeaders(basic())->postJson(BASE.'/payment', paymentBody());

    $r->assertOk();
    expect($r->json('code'))->toBe(0)
        ->and($r->json('data.payment_status'))->toBe(SimPayment::ENTERED)
        ->and($r->json('data.doc_id'))->toStartWith('1180_');
});

it('settles only after the delay has passed', function () {
    config(['simulator.aloqabank.settle_after_seconds' => 10]);

    $body = paymentBody();
    withHeaders(basic())->postJson(BASE.'/payment', $body);

    $still = withHeaders(basic())->getJson(BASE.'/payment/'.$body['orderId']);
    expect($still->json('data.payment_status'))->toBe(SimPayment::ENTERED);

    $this->travel(11)->seconds();

    $done = withHeaders(basic())->getJson(BASE.'/payment/'.$body['orderId']);
    expect($done->json('data.payment_status'))->toBe(SimPayment::EXECUTED);
});

it('refuses a duplicate orderId', function () {
    $body = paymentBody();

    withHeaders(basic())->postJson(BASE.'/payment', $body)->assertOk();
    $second = withHeaders(basic())->postJson(BASE.'/payment', $body);

    // 1111 tells the caller to query status by orderId rather than retry —
    // which is the correct handling for a duplicate.
    expect($second->json('status'))->toBe('error')
        ->and($second->json('code'))->toBe(ErrorCode::SYSTEM_ERROR)
        ->and(SimPayment::where('order_id', $body['orderId'])->count())->toBe(1);
});

it('debits the service balance by amount plus commission', function () {
    $before = SimService::find(33)->balance;

    withHeaders(basic())->postJson(BASE.'/payment', paymentBody([
        'amount' => '1000000', 'comissionAmount' => '25000',
    ]))->assertOk();

    expect(SimService::find(33)->balance)->toBe($before - 1_025_000);
});

it('reports 1017 when a required field is missing', function () {
    $r = withHeaders(basic())->postJson(BASE.'/payment', paymentBody(['mfoReceiver' => '']));

    expect($r->json('code'))->toBe(ErrorCode::MISSING_REQUIRED_FIELD);
});

it('requires the card fields only for a WORKING_WITH_CARD service', function () {
    // Service 34 is WORKING_WITH_CARD — no card details, so 1017.
    $without = withHeaders(basic())->postJson(BASE.'/payment', paymentBody(['serviceId' => '34']));
    expect($without->json('code'))->toBe(ErrorCode::MISSING_REQUIRED_FIELD);

    $with = withHeaders(basic())->postJson(BASE.'/payment', paymentBody([
        'serviceId' => '34', 'refNumber' => '016351273642',
        'cardType' => 'UZCARD', 'cardNumber' => '8600123456789012',
    ]));
    expect($with->json('code'))->toBe(0);
});

it('rejects control characters in the receiver name and the purpose', function () {
    $name = withHeaders(basic())->postJson(BASE.'/payment', paymentBody(['receiverName' => "Bad\x07Name"]));
    expect($name->json('code'))->toBe(ErrorCode::NAME_HAS_CONTROL_CHARS);

    $purpose = withHeaders(basic())->postJson(BASE.'/payment', paymentBody(['purpose' => "Bad\x01Purpose"]));
    expect($purpose->json('code'))->toBe(ErrorCode::PURPOSE_HAS_CONTROL_CHARS);
});

it('forces the documented error for each magic receiver account', function (string $suffix, int $code) {
    $r = withHeaders(basic())->postJson(BASE.'/payment', paymentBody([
        'receiverAccount' => '2980100099024884'.$suffix,
    ]));

    expect($r->json('status'))->toBe('error')
        ->and($r->json('code'))->toBe($code);
})->with([
    ['0013', ErrorCode::ACCOUNT_NOT_FOUND],
    ['0014', ErrorCode::BANK_NOT_IN_SMP],
    ['1017', ErrorCode::MISSING_REQUIRED_FIELD],
    ['3333', ErrorCode::DOC_DATE_BEFORE_OPERATING_DAY],
    ['1111', ErrorCode::SYSTEM_ERROR],
    ['2222', ErrorCode::CRITICAL_ERROR],
    ['1008', ErrorCode::FETCH_FAILED],
]);

it('keeps a …7777 payment stuck at "Введен" indefinitely', function () {
    $body = paymentBody(['receiverAccount' => '29801000990248847777']);
    withHeaders(basic())->postJson(BASE.'/payment', $body)->assertOk();

    $this->travel(2)->days();

    $r = withHeaders(basic())->getJson(BASE.'/payment/'.$body['orderId']);

    // Polling must give up eventually rather than loop forever.
    expect($r->json('data.payment_status'))->toBe(SimPayment::ENTERED);
});

it('rejects a …6666 payment to "Удален" and returns the money', function () {
    $before = SimService::find(33)->balance;
    $body = paymentBody(['receiverAccount' => '29801000990248846666', 'amount' => '700000']);

    withHeaders(basic())->postJson(BASE.'/payment', $body)->assertOk();
    expect(SimService::find(33)->balance)->toBe($before - 700_000);

    $this->travel(11)->seconds();
    $r = withHeaders(basic())->getJson(BASE.'/payment/'.$body['orderId']);

    expect($r->json('data.payment_status'))->toBe(SimPayment::DELETED)
        ->and(SimService::find(33)->balance)->toBe($before);
});

it('returns a malformed body for the …8888 account', function () {
    $r = withHeaders(basic())->post(BASE.'/payment', paymentBody([
        'receiverAccount' => '29801000990248848888',
    ]));

    $r->assertOk();
    expect(json_decode($r->getContent(), true))->toBeNull();
});

it('accepts an underfunded order and then rejects it', function () {
    SimService::whereKey(33)->update(['balance' => 100]);
    $body = paymentBody(['amount' => '900000']);

    $created = withHeaders(basic())->postJson(BASE.'/payment', $body);
    expect($created->json('data.payment_status'))->toBe(SimPayment::ENTERED);

    $this->travel(11)->seconds();
    $r = withHeaders(basic())->getJson(BASE.'/payment/'.$body['orderId']);

    expect($r->json('data.payment_status'))->toBe(SimPayment::DELETED)
        ->and(SimService::find(33)->balance)->toBe(100);   // never debited
});

it('returns 1008 for an unknown orderId', function () {
    $r = withHeaders(basic())->getJson(BASE.'/payment/NOPE-12345');

    expect($r->json('code'))->toBe(ErrorCode::FETCH_FAILED);
});

it('creates a budget payment with a bare doc_id', function () {
    $r = withHeaders(basic())->postJson(BASE.'/paymentBudget', [
        'orderId' => 'BUD-'.uniqid(),
        'amount' => '500000',
        'comissionAmount' => '20000',
        'purpose' => 'За патент за январь 2026 года',
        'purposeCode' => '09510',
        'innReceiver' => '207099143',
        'receiverAccount' => '400522860262837950100251028',
        'serviceId' => '33',
    ]);

    expect($r->json('code'))->toBe(0)
        ->and($r->json('data.doc_id'))->not->toContain('_');
});

it('returns the balance as a string with no code field', function () {
    $r = withHeaders(basic())->getJson(BASE.'/account/33/balance');

    // Both quirks are the bank's, reproduced deliberately.
    expect($r->json('data.balance'))->toBeString()
        ->and($r->json())->not->toHaveKey('code');
});

it('reports 1001 for an unknown service', function () {
    $r = withHeaders(basic())->getJson(BASE.'/account/999/balance');

    expect($r->json('code'))->toBe(ErrorCode::SERVICE_NOT_FOUND);
});

it('returns a statement of created payments', function () {
    withHeaders(basic())->postJson(BASE.'/payment', paymentBody())->assertOk();

    $r = withHeaders(basic())->postJson(BASE.'/account/payments', [
        'type' => '0',
        'lastId' => 0,
        'fromDate' => now()->subDay()->toDateString(),
        'toDate' => now()->addDay()->toDateString(),
        'serviceId' => 33,
    ]);

    $r->assertOk();
    expect($r->json('data'))->toHaveCount(1)
        ->and($r->json('data.0.amount'))->toBeInt()      // integer, unlike balance
        ->and($r->json('data.0.type'))->toBe('Расход');
});

it('scopes every resource to the authenticated partner', function () {
    $other = SimPartner::create(['name' => 'Someone else', 'username' => 'other', 'password' => 'pw']);
    SimService::create([
        'id' => 900, 'partner_id' => $other->id, 'name' => 'Theirs',
        'type' => SimService::CARD_IS_OPTIONAL, 'account' => '00000000000000000000', 'balance' => 1000,
    ]);

    $r = withHeaders(basic())->getJson(BASE.'/account/900/balance');

    expect($r->json('code'))->toBe(ErrorCode::SERVICE_NOT_FOUND);
});

it('is not registered when the simulator is disabled', function () {
    // Guards the production gate: the routes must simply not exist.
    expect(config('simulator.aloqabank.enabled'))->toBeTrue();

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn ($u) => str_starts_with($u, 'sim/'));

    expect($routes)->toHaveCount(6);
});
