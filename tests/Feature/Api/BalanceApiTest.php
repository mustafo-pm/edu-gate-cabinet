<?php

declare(strict_types=1);

use App\Enums\LedgerType;
use App\Enums\PspStatus;
use App\Models\Deposit;
use App\Models\Psp;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeaders;

/**
 * GET /api/v1/balance
 *
 * Every payment is refused once the prepaid deposit reaches zero, so a PSP has
 * to be able to watch it from their own systems rather than by opening the
 * cabinet.
 */
it('reports the prepaid balance in tiyin', function () {
    $w = apiWorld();
    $token = tokenFor($w);

    withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/balance')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.balance', 10_000_000)
        ->assertJsonPath('data.currency', 'UZS');
});

it('reports the newest ledger movement', function () {
    $w = apiWorld();

    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $w['psp']->id, 'type' => LedgerType::Debit,
        'amount' => 2_500_000, 'balance_after' => 7_500_000,
        'reference' => 'PT-77', 'description' => 'Payment debit',
    ]);

    $token = tokenFor($w);

    withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/balance')
        ->assertOk()
        // Read off the newest row's running balance, not by summing the ledger:
        // a second way to compute the same number is a second way to disagree.
        ->assertJsonPath('data.balance', 7_500_000)
        ->assertJsonPath('data.last_movement.type', 'debit')
        ->assertJsonPath('data.last_movement.amount', 2_500_000)
        ->assertJsonPath('data.last_movement.reference', 'PT-77');
});

it('reports zero for a PSP that has never funded', function () {
    $w = apiWorld();

    Deposit::withoutGlobalScopes()->where('psp_id', $w['psp']->id)->delete();

    $token = tokenFor($w);

    withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/balance')
        ->assertOk()
        ->assertJsonPath('data.balance', 0)
        ->assertJsonPath('data.last_movement', null);
});

it('never shows one provider the balance of another', function () {
    $w = apiWorld();

    $other = Psp::create(['name' => 'Rival', 'code' => 'rival', 'status' => PspStatus::Active]);
    Deposit::withoutGlobalScopes()->create([
        'psp_id' => $other->id, 'type' => LedgerType::Credit,
        'amount' => 999_000_000, 'balance_after' => 999_000_000,
    ]);

    $token = tokenFor($w);

    withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/balance')
        ->assertOk()
        ->assertJsonPath('data.balance', 10_000_000);
});

it('refuses an unauthenticated caller', function () {
    getJson('/api/v1/balance')->assertStatus(401);
});
