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

/*
 * A funded PSP with a live API key, an institution, and a student who owes
 * tuition — the smallest world in which a payment can happen.
 *
 * Lives here rather than inside one test file because more than one suite needs
 * it: a helper declared in a test file only exists while that file is running.
 */
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
