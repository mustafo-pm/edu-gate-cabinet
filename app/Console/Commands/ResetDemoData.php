<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ApiEnvironment;
use App\Enums\BranchMatchStatus;
use App\Enums\LedgerType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Enums\ScheduleStatus;
use App\Models\ApiKey;
use App\Models\BankBranch;
use App\Models\BankTransfer;
use App\Models\Deposit;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\PaymentSchedule;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Psp;
use App\Models\PspUser;
use App\Models\SettlementAccount;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Wipes tenant + money data and lays down one clean demo tenant.
 *
 * ⚠️ This DELETES transactions, deposits, payouts and postings — tables that
 * are append-only by design and that no other code path is ever allowed to
 * remove rows from. It exists only because the platform is pre-launch and the
 * data on the server is demonstration material, not anyone's real money. Once
 * a real institution is onboarded this command must be removed, not guarded.
 *
 * Reference and configuration data is deliberately kept: banks, MFO branches,
 * our settlement accounts, admin users, website partners, alert rules and
 * Telegram destinations all survive.
 */
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset
        {--force : Required to run when APP_ENV=production}
        {--password= : Password for the demo logins (default: a random one, printed once)}';

    protected $description = 'Delete all tenant/money data and seed one university, three students and one PSP';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('APP_ENV=production. This DELETES financial records. Re-run with --force if that is really what you want.');

            return self::FAILURE;
        }

        $this->warn('This deletes every transaction, deposit, payout, posting, student, institution and PSP.');

        if (! $this->option('no-interaction') && ! $this->confirm('Continue?', false)) {
            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(16, symbols: false);

        DB::transaction(function () {
            // Order matters: children before parents, since money tables use
            // restrictOnDelete precisely to stop accidental cascades.
            BankTransfer::query()->delete();
            PayoutItem::query()->delete();
            Payout::query()->delete();
            Deposit::withoutGlobalScopes()->delete();
            Transaction::withoutGlobalScopes()->delete();
            PaymentSchedule::withoutGlobalScopes()->delete();
            Student::withoutGlobalScopes()->delete();
            MerchantUser::query()->delete();
            ApiKey::withoutGlobalScopes()->delete();
            PspUser::query()->delete();
            Merchant::withoutGlobalScopes()->delete();
            Psp::withoutGlobalScopes()->delete();
        });

        $this->info('Cleared.');

        $branch = $this->payableBranch();

        $merchant = Merchant::create([
            'name' => 'Toshkent Davlat Universiteti',
            'type' => MerchantType::University,
            'status' => MerchantStatus::Active,
            'stir' => '301234567',
            // Must match a CONFIRMED branch or every settlement blocks.
            'mfo' => $branch->mfo,
            'bank_account' => '20208000900123456789',
            'bank_name' => $branch->bank?->name_uz,
            'contact_name' => 'Moliya bo\'limi',
            'contact_email' => 'finance@tdu.uz',
            'contact_phone' => '+998 71 000 00 01',
        ]);

        MerchantUser::create([
            'merchant_id' => $merchant->id, 'name' => 'TDU Finance',
            'email' => 'merchant@edu-gate.uz', 'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $students = [
            ['STU-0001', 'Malika', 'Yusupova', 600_000_000],
            ['STU-0002', 'Sardor', 'Rahimov', 450_000_000],
            ['STU-0003', 'Nilufar', 'Ahmedova', 750_000_000],
        ];

        foreach ($students as [$number, $first, $last, $tuition]) {
            $student = Student::withoutGlobalScopes()->create([
                'merchant_id' => $merchant->id,
                'student_id_number' => $number,
                'first_name' => $first,
                'last_name' => $last,
            ]);

            PaymentSchedule::withoutGlobalScopes()->create([
                'merchant_id' => $merchant->id,
                'student_id' => $student->id,
                'title' => '2026/2027 tuition',
                'period' => '2026/2027',
                'amount' => $tuition,
                'paid_amount' => 0,
                'due_date' => now()->addMonths(2)->toDateString(),
                'status' => ScheduleStatus::Unpaid,
            ]);
        }

        $psp = Psp::create([
            'name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active,
            'commission_bps' => 150,
            'contact_email' => 'api@clickpay.uz',
        ]);

        PspUser::create([
            'psp_id' => $psp->id, 'name' => 'ClickPay Ops',
            'email' => 'psp@edu-gate.uz', 'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $secret = 'sk_sandbox_'.Str::random(40);
        $key = ApiKey::withoutGlobalScopes()->create([
            'psp_id' => $psp->id,
            'name' => 'Sandbox',
            'key_id' => 'egk_'.Str::random(24),
            'secret_hash' => Hash::make($secret),
            'environment' => ApiEnvironment::Sandbox,
        ]);

        // Float so /confirm is not immediately blocked by an empty deposit.
        $float = 5_000_000_000;
        Deposit::withoutGlobalScopes()->create([
            'psp_id' => $psp->id, 'type' => LedgerType::Credit,
            'amount' => $float, 'balance_after' => $float,
            'reference' => 'DEMO-FLOAT', 'description' => 'Demo opening balance',
        ]);

        $this->newLine();
        $this->info('Demo tenant ready.');
        $this->table(['What', 'Value'], [
            ['Institution', $merchant->name.'  (id '.$merchant->id.')'],
            ['MFO / account', $branch->mfo.' · '.$merchant->bank_account],
            ['Branch status', $branch->match_status->value.' — payable'],
            ['Students', 'STU-0001, STU-0002, STU-0003'],
            ['PSP', $psp->name.'  (id '.$psp->id.')'],
            ['PSP deposit', number_format($float / 100).' UZS'],
            ['API key_id', $key->key_id],
            ['API secret', $secret],
            ['merchant login', 'merchant@edu-gate.uz'],
            ['psp login', 'psp@edu-gate.uz'],
            ['password', $password],
        ]);
        $this->warn('The API secret is shown ONCE — only its hash is stored. Change the demo passwords before any real institution is onboarded.');

        return self::SUCCESS;
    }

    /**
     * A branch money may actually be routed to.
     *
     * Prefers one a human already confirmed. If none exists it confirms the
     * branch behind our default settlement account and says so loudly — an
     * unconfirmed MFO would leave every posting blocked, which makes for a
     * confusing demo, but silently confirming one is not something to hide.
     */
    private function payableBranch(): BankBranch
    {
        $confirmed = BankBranch::with('bank')
            ->where('match_status', BranchMatchStatus::Confirmed)
            ->first();

        if ($confirmed) {
            return $confirmed;
        }

        $account = SettlementAccount::where('is_active', true)->where('is_default', true)->first();

        $branch = BankBranch::with('bank')
            ->when($account, fn ($q) => $q->where('bank_id', $account->bank_id))
            ->first();

        if (! $branch) {
            $this->error('No bank branches at all — import the MFO registry first.');
            exit(self::FAILURE);
        }

        $branch->update(['match_status' => BranchMatchStatus::Confirmed]);
        $this->warn("No confirmed branch existed, so MFO {$branch->mfo} was confirmed for this demo.");

        return $branch;
    }
}
