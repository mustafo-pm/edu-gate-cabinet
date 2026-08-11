<?php

declare(strict_types=1);

use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeaders;

it('issues a token for valid API key credentials', function () {
    $w = apiWorld();

    postJson('/api/v1/auth/login', ['key_id' => 'egk_test', 'secret' => $w['secret']])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('data.environment', 'sandbox')
        ->assertJsonStructure(['data' => ['access_token', 'token_type']]);
});

it('rejects a bad secret', function () {
    apiWorld();

    postJson('/api/v1/auth/login', ['key_id' => 'egk_test', 'secret' => 'wrong'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('runs the full check → confirm flow and returns tiyin amounts', function () {
    $w = apiWorld();
    $token = tokenFor($w);

    $check = withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/payments/check', [
            'institution_id' => $w['merchant']->id,
            'student_id_number' => 'STU-0001',
        ])->assertOk();

    $checkId = $check->json('data.check_id');
    expect($check->json('data.amount_owed'))->toBe(6_000_000);

    withHeaders(['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'idem-1'])
        ->postJson('/api/v1/payments/confirm', [
            'check_id' => $checkId,
            'partner_transaction_id' => 'PSP-1',
            'amount' => 6_000_000,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.commission_amount', 90_000)
        ->assertJsonPath('data.net_amount', 5_910_000)
        ->assertJsonPath('data.status', 'completed');
});

it('rejects confirm without an Idempotency-Key header', function () {
    $w = apiWorld();
    $token = tokenFor($w);

    withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/payments/confirm', [
            'check_id' => 'chk_whatever',
            'partner_transaction_id' => 'PSP-2',
            'amount' => 6_000_000,
        ])
        ->assertStatus(428)
        ->assertJsonPath('error.code', 'idempotency_key_required');
});

it('requires authentication', function () {
    apiWorld();
    postJson('/api/v1/payments/check', [])->assertUnauthorized();
});
