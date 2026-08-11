<?php

namespace Database\Seeders;

use App\Enums\ApiEnvironment;
use App\Enums\CommissionScope;
use App\Enums\LedgerType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\ScheduleStatus;
use App\Enums\StudentStatus;
use App\Enums\TransactionStatus;
use App\Models\AdminUser;
use App\Models\ApiKey;
use App\Models\CommissionRule;
use App\Models\Department;
use App\Models\Deposit;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaymentSchedule;
use App\Models\Psp;
use App\Models\PspUser;
use App\Models\Student;
use App\Models\Transaction;
use App\Notifications\CabinetNotification;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── EduGate internal admin ──────────────────────────────
        AdminUser::updateOrCreate(
            ['email' => 'admin@edu-gate.uz'],
            ['name' => 'EduGate Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );

        // ── Global commission rule (1.5%) ───────────────────────
        CommissionRule::updateOrCreate(
            ['scope' => CommissionScope::Global, 'merchant_id' => null, 'psp_id' => null, 'category' => null],
            ['rate_bps' => 150, 'fixed_fee' => 0, 'priority' => 10, 'is_active' => true],
        );

        // ── An institution (merchant) ───────────────────────────
        $merchant = Merchant::updateOrCreate(
            ['name' => 'Toshkent Davlat Universiteti'],
            [
                'type' => MerchantType::University,
                'status' => MerchantStatus::Active,
                'stir' => '301234567',
                'mfo' => '00014',
                'bank_account' => '20208000900123456789',
                'bank_name' => 'Xalq Banki',
                'commission_bps' => 150,
                'contact_name' => 'Aziza Karimova',
                'contact_phone' => '+998 71 200 00 00',
                'contact_email' => 'finance@tdu.uz',
            ],
        );

        $merchantUser = MerchantUser::updateOrCreate(
            ['email' => 'merchant@edu-gate.uz'],
            [
                'merchant_id' => $merchant->id,
                'name' => 'Aziza Karimova',
                'phone' => '+998 90 123 45 67',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        $faculty = Department::updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Faculty of Economics'],
            ['code' => 'ECON'],
        );

        // Students + a September tuition schedule each.
        $names = [
            ['Jasur', 'Toshmatov', 'Alisher oʻgʻli'],
            ['Malika', 'Yusupova', 'Bahodir qizi'],
            ['Sardor', 'Rahimov', 'Akmal oʻgʻli'],
            ['Nilufar', 'Ahmedova', 'Rustam qizi'],
        ];
        foreach ($names as $i => [$first, $last, $middle]) {
            $student = Student::updateOrCreate(
                ['merchant_id' => $merchant->id, 'student_id_number' => 'STU-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'department_id' => $faculty->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'middle_name' => $middle,
                    'status' => StudentStatus::Active,
                    'parent_name' => $last.' (parent)',
                    'parent_phone' => '+998 90 000 00 0'.$i,
                ],
            );

            PaymentSchedule::updateOrCreate(
                ['student_id' => $student->id, 'period' => '2026-09'],
                [
                    'merchant_id' => $merchant->id,
                    'title' => 'Tuition — September 2026',
                    'amount' => Money::toTiyin(6_000_000), // 6,000,000 UZS
                    'paid_amount' => $i === 0 ? Money::toTiyin(6_000_000) : 0,
                    'due_date' => '2026-09-10',
                    'status' => $i === 0 ? ScheduleStatus::Paid : ScheduleStatus::Unpaid,
                ],
            );
        }

        // ── A payment service provider ──────────────────────────
        $psp = Psp::updateOrCreate(
            ['code' => 'clickpay'],
            [
                'name' => 'ClickPay',
                'status' => PspStatus::Active,
                'commission_bps' => 0,
                'contact_name' => 'Dilshod Nazarov',
                'contact_email' => 'api@clickpay.uz',
                // No webhook_url on purpose. A seeded address that looks real
                // but is not an endpoint is a trap: the first person to switch
                // delivery on starts POSTing payment data at somebody's home
                // page. A PSP sets their own address in the partner cabinet.
            ],
        );

        $pspUser = PspUser::updateOrCreate(
            ['email' => 'psp@edu-gate.uz'],
            [
                'psp_id' => $psp->id,
                'name' => 'Dilshod Nazarov',
                'phone' => '+998 90 555 44 33',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        // Sandbox API key (secret shown only here in the seeder output).
        if (! $psp->apiKeys()->where('environment', ApiEnvironment::Sandbox->value)->exists()) {
            $secret = 'sk_sandbox_'.Str::random(40);
            ApiKey::create([
                'psp_id' => $psp->id,
                'name' => 'Sandbox key',
                'key_id' => 'egk_'.Str::random(24),
                'secret_hash' => Hash::make($secret),
                'environment' => ApiEnvironment::Sandbox,
            ]);
            $this->command?->info("PSP sandbox API secret (save it): {$secret}");
        }

        // Seed ~15 months of history so dashboards/analytics are populated,
        // keeping the append-only deposit ledger consistent (credits then debits).
        if ($psp->deposits()->count() === 0) {
            $this->seedHistory($psp, $merchant);
        }

        // ── Demo in-app notifications ───────────────────────────
        if ($merchantUser->notifications()->count() === 0) {
            $merchantUser->notify(new CabinetNotification(
                'New payment received', 'Malika Yusupova paid 60 000.00 UZS via ClickPay.', 'success'));
            $merchantUser->notify(new CabinetNotification(
                'Tuition overdue', '3 students have overdue September schedules.', 'warning'));
            $merchantUser->notify(new CabinetNotification(
                'Welcome to EduGate', 'Your institution cabinet is ready. Import your students to get started.', 'info'));
            // mark the last (welcome) as already read for a realistic mix
            $merchantUser->notifications()->latest()->first()?->markAsRead();
        }

        if ($pspUser->notifications()->count() === 0) {
            $pspUser->notify(new CabinetNotification(
                'Low deposit balance', 'Your prepaid balance is below 5 000 000 UZS. Top up to avoid declines.', 'danger'));
            $pspUser->notify(new CabinetNotification(
                'Settlement completed', 'Batch settlement to Toshkent Davlat Universiteti succeeded.', 'success'));
            $pspUser->notify(new CabinetNotification(
                'New API key created', 'A sandbox API key was generated for your account.', 'info'));
        }

        $this->command?->info('Seed complete. Logins (password: "password"):');
        $this->command?->line('  admin@edu-gate.uz     → /admin');
        $this->command?->line('  merchant@edu-gate.uz  → /merchant');
        $this->command?->line('  psp@edu-gate.uz       → /partner');
    }

    /** Seed 15 months of completed transactions + a consistent deposit ledger. */
    private function seedHistory(Psp $psp, Merchant $merchant): void
    {
        $students = $merchant->students()->withoutGlobalScopes()->get();
        if ($students->isEmpty()) {
            return;
        }

        $amounts = [3_500_000, 4_000_000, 6_000_000, 7_500_000]; // som
        $balance = 0;
        $seq = 0;

        for ($m = 14; $m >= 0; $m--) {
            $month = Carbon::create(2026, 7, 1)->subMonths($m);

            // Monthly top-up (credit) — grows over time to mimic a scaling PSP.
            $topup = Money::toTiyin(90_000_000 + (14 - $m) * 6_000_000);
            $balance += $topup;
            Deposit::create([
                'psp_id' => $psp->id,
                'type' => LedgerType::Credit,
                'amount' => $topup,
                'balance_after' => $balance,
                'reference' => 'TOPUP-'.$month->format('Y-m'),
                'description' => 'Monthly prepaid top-up',
                'created_at' => $month->copy()->startOfMonth(),
                'updated_at' => $month->copy()->startOfMonth(),
            ]);

            // Rising payment volume across the months.
            $count = 8 + intdiv((14 - $m), 2) + ($m % 3);
            for ($i = 0; $i < $count; $i++) {
                $student = $students->random();
                $amount = Money::toTiyin($amounts[array_rand($amounts)]);
                if ($balance < $amount) {
                    break;
                }
                $commission = intdiv($amount * $merchant->commission_bps, 10000);
                $paidAt = $month->copy()->startOfMonth()->addDays(random_int(0, 26))->addHours(random_int(8, 19));
                $seq++;

                $txn = Transaction::withoutGlobalScopes()->create([
                    'psp_id' => $psp->id,
                    'merchant_id' => $merchant->id,
                    'student_id' => $student->id,
                    'partner_transaction_id' => 'HIST-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                    'amount' => $amount,
                    'commission_amount' => $commission,
                    'net_amount' => $amount - $commission,
                    'status' => TransactionStatus::Completed,
                    'gateway' => 'clickpay',
                    'paid_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);

                $balance -= $amount;
                Deposit::create([
                    'psp_id' => $psp->id,
                    'type' => LedgerType::Debit,
                    'amount' => $amount,
                    'balance_after' => $balance,
                    'transaction_id' => $txn->id,
                    'reference' => $txn->partner_transaction_id,
                    'description' => 'Payment debit',
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);
            }
        }
    }
}
