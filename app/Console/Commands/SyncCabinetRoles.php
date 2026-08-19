<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CabinetRoles;
use Illuminate\Console\Command;

/**
 * Creates the cabinet roles and gives existing accounts the owner role.
 *
 * Must be run on the deploy that introduces permissions. Until it has, every
 * merchant route is gated on a permission nobody holds, and institutions are
 * locked out of their own cabinets.
 */
class SyncCabinetRoles extends Command
{
    protected $signature = 'edugate:roles {--no-backfill : Create roles but leave existing accounts alone}';

    protected $description = 'Create merchant cabinet roles and permissions';

    public function handle(): int
    {
        CabinetRoles::sync();

        $this->info('Roles and permissions are in place.');

        foreach (CabinetRoles::roles() as $role => $permissions) {
            $this->line(sprintf('  <fg=green>%-11s</> %s', $role, implode(', ', $permissions)));
        }

        if ($this->option('no-backfill')) {
            $this->newLine();
            $this->warn('Skipped the backfill. Accounts without a role can reach nothing.');

            return self::SUCCESS;
        }

        $changed = CabinetRoles::backfillOwners();

        $this->newLine();
        $this->info($changed === 0
            ? 'Every cabinet account already has a role.'
            : "Gave the owner role to {$changed} account(s) that had none.");

        return self::SUCCESS;
    }
}
