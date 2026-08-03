<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Bank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Copies bank logo files into storage and points each bank at its own.
 *
 *   php artisan edugate:import-bank-logos "/path/to/Uz bank logos"
 *
 * Files are stored as bank-logos/{slug}.{ext} so a re-import simply replaces
 * the previous file. Matching is by slug, with aliases for the cases where the
 * supplied filename is a trade name rather than the registered bank name.
 */
class ImportBankLogos extends Command
{
    protected $signature = 'edugate:import-bank-logos {path : Folder containing the logo files}';

    protected $description = 'Import bank logo files and link them to banks';

    /** slug => filename stem, where the file is not named after the bank. */
    private const ALIASES = [
        'ofb' => 'orientfinance',          // Orient Finance Bank
        'ipakyolibank' => 'ipakyuli',      // spelled "Ipak Yuli" in the logo pack
        'madad-invest-bank' => 'mybank',   // Madad Invest trades as Mybank
    ];

    /** Files deliberately not imported. */
    private const SKIP = ['hamkorbankold'];

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');
        if (! is_dir($path)) {
            $this->error("Folder not found: {$path}");

            return self::FAILURE;
        }

        $files = collect(glob($path.'/*.{svg,png,webp,jpg,jpeg}', GLOB_BRACE));
        if ($files->isEmpty()) {
            $this->error('No image files found in that folder.');

            return self::FAILURE;
        }

        $imported = 0;
        $missing = [];
        $usedFiles = [];

        foreach (Bank::orderBy('name_uz')->get() as $bank) {
            // Normalise the alias too — otherwise "mybank" never equals the
            // normalised filename "my" (the suffix is stripped on both sides).
            $key = $this->normalise(self::ALIASES[$bank->slug] ?? $bank->slug);

            $file = $files->first(fn ($f) => $this->normalise(pathinfo($f, PATHINFO_FILENAME)) === $key)
                ?? $files->first(fn ($f) => strlen($key) > 3
                    && ! in_array($this->normalise(pathinfo($f, PATHINFO_FILENAME)), self::SKIP, true)
                    && str_contains($this->normalise(pathinfo($f, PATHINFO_FILENAME)), $key));

            if (! $file) {
                $missing[] = $bank->name_uz;

                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $target = "bank-logos/{$bank->slug}.{$ext}";

            Storage::disk('public')->put($target, (string) file_get_contents($file));
            $bank->update(['logo_path' => $target]);
            $usedFiles[] = $file;
            $imported++;
        }

        // Compare against the SOURCE files actually consumed, not the renamed
        // targets — the two never match after slug renaming.
        $unused = $files
            ->reject(fn ($f) => in_array($f, $usedFiles, true))
            ->map(fn ($f) => pathinfo($f, PATHINFO_FILENAME))
            ->values();

        $this->newLine();
        $this->info("Logos imported: {$imported} of ".Bank::count().' banks');
        if ($missing) {
            $this->warn('No logo found for: '.implode(', ', $missing));
        }
        if ($unused->isNotEmpty()) {
            $this->warn('Logo files not used: '.$unused->implode(', '));
        }
        $this->line('Stored on the public disk under bank-logos/.');

        return self::SUCCESS;
    }

    private function normalise(string $value): string
    {
        $value = preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';

        return preg_replace('/(bank|banki)$/', '', $value) ?? $value;
    }
}
