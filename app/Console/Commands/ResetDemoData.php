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
use App\Models\Bank;
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
use App\Services\A2a\A2aDriverManager;
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

    /** Marks an account this command created, and may therefore correct. */
    private const DEMO_LABEL = 'DEMO — ';

    public function __construct(private readonly A2aDriverManager $drivers)
    {
        parent::__construct();
    }

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

        $rail = $this->rail();

        if (! $rail) {
            return self::FAILURE;
        }

        $branch = $this->payableBranch($rail);

        if (! $branch) {
            return self::FAILURE;
        }

        $account = $this->settlementAccount($rail);

        if (! $account) {
            return self::FAILURE;
        }

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
            ['Sends from', $account->label.'  ·  '.$account->account],
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
     * Correct a demo account this command created at the wrong bank.
     *
     * The earlier bug put our settlement account at the RECIPIENT's bank rather
     * than the rail's. Fixing the creation path was not enough: an account
     * already existed on the server, so the early return handed it straight back
     * and the wrong row survived every subsequent reset.
     *
     * Only rows this command labelled DEMO are touched. A real account holds
     * someone's typed-in banking requisites — it is reported and left alone,
     * because silently rewriting where money leaves from is never this
     * command's call to make.
     */
    private function healDemoAccount(SettlementAccount $account, Bank $rail): SettlementAccount
    {
        if ($account->bank_id === $rail->id) {
            return $account;
        }

        if (! str_starts_with((string) $account->label, self::DEMO_LABEL)) {
            $this->warn("Settlement account '{$account->label}' is not at {$rail->name_uz}, the bank we hold a driver for.");
            $this->warn('It carries real requisites, so it has been left untouched. Check Accounting → Our accounts.');

            return $account;
        }

        $account->forceFill([
            'bank_id' => $rail->id,
            'label' => self::DEMO_LABEL.$rail->name_uz.' simulator',
            'mfo' => BankBranch::where('bank_id', $rail->id)->value('mfo') ?? $rail->code,
            'driver' => $rail->a2a_driver,
        ])->save();

        $this->warn("Demo settlement account moved to {$rail->name_uz} — it was at the wrong bank.");

        return $account;
    }

    /**
     * The account we send FROM. Without one, every posting blocks on
     * "No active settlement account to send from for this bank" — which is a
     * correct refusal but makes for a demo that provably cannot complete.
     *
     * Only created when none exists, and only ever pointed at the simulator.
     * These are placeholder requisites for a fake bank; EduGate's real account
     * details must be entered by a human in Accounting → Our accounts before
     * anything is sent to a real one.
     */
    private function settlementAccount(Bank $rail): ?SettlementAccount
    {
        $existing = SettlementAccount::where('is_active', true)->first();

        if ($existing) {
            return $this->healDemoAccount($existing, $rail);
        }

        $base = (string) config('services.aloqabank.base_url');

        if (! str_contains($base, '/sim/')) {
            $this->error('No settlement account exists and the A2A driver points at a REAL bank.');
            $this->error('Refusing to invent account details. Add one in Accounting → Our accounts.');

            return null;
        }

        // Our account lives at the RAIL bank. Creating it at the recipient's
        // bank instead produced a row claiming an Aloqabank integration at a
        // bank we have no relationship with.
        $account = SettlementAccount::create([
            'bank_id' => $rail->id,
            'label' => self::DEMO_LABEL.$rail->name_uz.' simulator',
            // Matches the partner account the simulator seeds for service 33.
            'account' => '20208000405273320010',
            'mfo' => BankBranch::where('bank_id', $rail->id)->value('mfo') ?? $rail->code,
            'tax' => '301234567',
            'holder_name' => 'EduGate LLC (demo)',
            'driver' => $rail->a2a_driver,
            'balance' => 500_000_000_000,
            'balance_updated_at' => now(),
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->warn('No settlement account existed, so a DEMO one was created against the simulator.');
        $this->warn('Replace it with EduGate\'s real requisites before pointing the driver at a real bank.');

        return $account;
    }

    /**
     * A branch money may actually be routed to.
     *
     * Order matters, and getting it wrong produces a demo that cannot possibly
     * work: the branch has to belong to a bank we hold a DRIVER for, because
     * that is the only rail a posting can leave on. Picking "the first branch
     * in the table" lands on an arbitrary one of 38 banks and every posting
     * then blocks for want of a settlement account.
     */
    private function payableBranch(Bank $rail): ?BankBranch
    {
        $confirmed = BankBranch::with('bank')
            ->where('bank_id', $rail->id)
            ->where('match_status', BranchMatchStatus::Confirmed)
            ->first()
            // Fall back to any confirmed branch only if our own bank has none.
            ?? BankBranch::with('bank')->where('match_status', BranchMatchStatus::Confirmed)->first();

        if ($confirmed) {
            return $confirmed;
        }

        $branch = BankBranch::with('bank')
            ->where('bank_id', $rail->id)
            ->first();

        if (! $branch) {
            $this->error('No branches for a bank we can send from — import the MFO registry first.');

            return null;
        }

        $branch->update(['match_status' => BranchMatchStatus::Confirmed]);
        $this->warn("No confirmed branch existed, so MFO {$branch->mfo} was confirmed for this demo.");

        return $branch;
    }

    /**
     * The bank we hold an A2A integration with — the only rail a posting can
     * leave on.
     *
     * On a fresh install NO bank carries a2a_driver: the flag is set by the
     * accounting demo seeder, which a real deploy never runs. Returning null
     * there made payableBranch() fall through to "the first branch in the
     * table" and settlementAccount() then created our account at whatever bank
     * that happened to be — a bank we hold no integration with. So this adopts
     * the bank matching a registered driver key instead of degrading quietly.
     */
    private function rail(): ?Bank
    {
        $flagged = Bank::where('is_active', true)
            ->whereNotNull('a2a_driver')
            ->orderByDesc('a2a_supported')
            ->first();

        if ($flagged) {
            return $flagged;
        }

        foreach ($this->drivers->keys() as $key) {
            $bank = Bank::where('slug', $key)->first();

            if (! $bank) {
                continue;
            }

            $bank->forceFill(['a2a_supported' => true, 'a2a_driver' => $key])->save();
            $this->warn("No bank was flagged for A2A, so {$bank->name_uz} was enabled with the '{$key}' driver.");

            return $bank;
        }

        $this->error('No bank matches any registered A2A driver ('.implode(', ', $this->drivers->keys()).').');
        $this->error('Import the bank registry, or flag the right bank in Banking → Banks.');

        return null;
    }
}
