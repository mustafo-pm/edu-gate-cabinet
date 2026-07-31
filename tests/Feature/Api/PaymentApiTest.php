<?php

declare(strict_types=1);

use App\Enums\ApiEnvironment;
use App\Enums\CommissionScope;
use App\Enums\LedgerType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\ScheduleStatus;
use App\Enums\StudentStatus;
use App\Models\ApiKey;
use App\Models\CommissionRule;
use App\Models\Deposit;
use App\Models\Merchant;
use App\Models\PaymentSchedule;
use App\Models\Psp;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeaders;

function apiWorld(): array
{
    CommissionRule::create(['scope' => CommissionScope::Global, 'rate_bps' => 150, 'priority' => 10, 'is_active' => true]);

    $psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);
    Deposit::create(['psp_id' => $psp->id, 'type' => LedgerType::Credit, 'amount' => 10_000_000, 'balance_after' => 10_000_000]);

    $secret = 'sk_sandbox_testsecret';
    $key = ApiKey::create([
        'psp_id' => $psp->id, 'name' => 'test', 'key_id' => 'egk_test',
        'secret_hash' => Hash::make($secret), 'environment' => ApiEnvironment::Sandbox,
    ]);

    $merchant = Merchant::create(['name' => 'TDU', 'type' => MerchantType::University, 'status' => MerchantStatus::Active, 'commission_bps' => 150]);
    $student = Student::create(['merchant_id' => $merchant->id, 'student_id_number' => 'STU-0001', 'first_name' => 'J', 'last_name' => 'T', 'status' => StudentStatus::Active]);
    PaymentSchedule::create(['merchant_id' => $merchant->id, 'student_id' => $student->id, 'title' => 'Tuition', 'amount' => 6_000_000, 'due_date' => '2026-09-10', 'status' => ScheduleStatus::Unpaid]);

    return compact('psp', 'key', 'secret', 'merchant', 'student');
}

function tokenFor(array $w): string
{
    return test()->postJson('/api/v1/auth/login', ['key_id' => $w['key']->key_id, 'secret' => $w['secret']])
        ->assertOk()
        ->json('data.access_token');
}

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
