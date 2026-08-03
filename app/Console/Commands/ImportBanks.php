<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BranchMatchStatus;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Support\BankNameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Imports the bank + MFO branch registry.
 *
 *   php artisan edugate:import-banks
 *
 * Re-runnable: rows are matched on their natural keys (bank code, branch MFO),
 * and a branch already Confirmed by a human is never downgraded by a re-import.
 */
class ImportBanks extends Command
{
    protected $signature = 'edugate:import-banks
        {--banks=database/data/bank.csv : Path to the banks CSV}
        {--branches=database/data/branches.csv : Path to the branches CSV}
        {--rematch : Re-run name matching over branches that are still unconfirmed}';

    protected $description = 'Import banks and MFO branches, deriving each branch\'s bank from its name';

    public function handle(): int
    {
        if (! $this->option('rematch')) {
            $this->importBanks();
        }

        $this->importBranches();
        $this->report();

        return self::SUCCESS;
    }

    /** bank.csv: id, code, name_uz, name_ru, name_en, logo, is_active, … */
    private function importBanks(): void
    {
        $path = base_path($this->option('banks'));
        if (! is_readable($path)) {
            $this->error("Banks file not found: {$path}");

            return;
        }

        $count = 0;
        foreach ($this->rows($path) as $r) {
            if (count($r) < 5 || trim($r[1]) === '') {
                continue;
            }

            $logo = trim($r[5] ?? '');
            $slug = $logo !== ''
                ? Str::of($logo)->afterLast('/')->beforeLast('.')->slug()->value()
                : Str::slug($r[4] ?: $r[2]);

            Bank::updateOrCreate(
                ['code' => trim($r[1])],
                [
                    'slug' => $slug,
                    'name_uz' => trim($r[2]),
                    'name_ru' => trim($r[3]) ?: null,
                    'name_en' => trim($r[4]) ?: null,
                    // Path only — the file itself arrives separately.
                    'logo_path' => $logo !== '' ? 'bank-logos/'.Str::of($logo)->afterLast('/') : null,
                    'is_active' => filter_var($r[6] ?? 'true', FILTER_VALIDATE_BOOL),
                ],
            );
            $count++;
        }

        BankNameMatcher::flush();
        $this->info("Banks imported/updated: {$count}");
    }

    /** branches.csv: id, mfo, (unused), name_uz, name_ru, name_en, is_active, … */
    private function importBranches(): void
    {
        $path = base_path($this->option('branches'));
        if (! is_readable($path)) {
            $this->error("Branches file not found: {$path}");

            return;
        }

        $slugToId = Bank::pluck('id', 'slug')->all();
        $created = 0;
        $matched = 0;
        $skippedConfirmed = 0;

        foreach ($this->rows($path) as $r) {
            if (count($r) < 6 || trim($r[1]) === '') {
                continue;
            }

            $mfo = str_pad(trim($r[1]), 5, '0', STR_PAD_LEFT);
            $existing = BankBranch::where('mfo', $mfo)->first();

            // Never overwrite a human decision.
            if ($existing && $existing->match_status === BranchMatchStatus::Confirmed) {
                $skippedConfirmed++;

                continue;
            }

            $names = trim($r[3]).' '.trim($r[4]).' '.trim($r[5]);
            $slug = BankNameMatcher::match($names);
            $bankId = $slug ? ($slugToId[$slug] ?? null) : null;

            if ($bankId) {
                $matched++;
            }

            BankBranch::updateOrCreate(
                ['mfo' => $mfo],
                [
                    'bank_id' => $bankId,
                    'name_uz' => trim($r[3]) ?: null,
                    'name_ru' => trim($r[4]) ?: null,
                    'name_en' => trim($r[5]) ?: null,
                    'match_status' => $bankId ? BranchMatchStatus::Auto : BranchMatchStatus::Unmapped,
                    'match_note' => $bankId ? 'Derived from branch name — needs confirmation' : 'No bank matched',
                    'is_active' => filter_var($r[6] ?? 'true', FILTER_VALIDATE_BOOL),
                ],
            );
            $created++;
        }

        $this->info("Branches processed: {$created} (auto-matched {$matched}, kept confirmed {$skippedConfirmed})");
    }

    private function report(): void
    {
        $total = BankBranch::count();
        $auto = BankBranch::where('match_status', BranchMatchStatus::Auto)->count();
        $confirmed = BankBranch::where('match_status', BranchMatchStatus::Confirmed)->count();
        $unmapped = BankBranch::where('match_status', BranchMatchStatus::Unmapped)->count();

        $this->newLine();
        $this->table(
            ['Status', 'Branches', '%'],
            [
                ['Confirmed (payable)', $confirmed, $this->pct($confirmed, $total)],
                ['Auto-matched (review)', $auto, $this->pct($auto, $total)],
                ['Unmapped (review)', $unmapped, $this->pct($unmapped, $total)],
                ['Total', $total, '100%'],
            ],
        );

        $zero = Bank::whereDoesntHave('branches')->pluck('name_uz')->all();
        if ($zero) {
            $this->warn('Banks with no branches matched: '.implode(', ', $zero));
        }

        $this->line('Review and confirm branches in the admin panel before routing money.');
    }

    private function pct(int $n, int $total): string
    {
        return $total > 0 ? number_format($n / $total * 100, 1).'%' : '—';
    }

    /** @return iterable<array<int, string>> */
    private function rows(string $path): iterable
    {
        $fh = fopen($path, 'r');
        try {
            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                if ($row !== [null]) {
                    yield $row;
                }
            }
        } finally {
            fclose($fh);
        }
    }
}
