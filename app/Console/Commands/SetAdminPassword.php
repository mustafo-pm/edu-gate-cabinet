<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rotates an admin panel password from the shell.
 *
 * Exists because the seeded admin account ships with the password `password`
 * and demo:reset deliberately does NOT touch admin users — it preserves them
 * along with the rest of the configuration. That left the one account with
 * full access to every tenant's financial data on a known credential, with no
 * obvious way to change it other than logging in with that same credential.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'edugate:admin-password
        {email : The admin account to rotate}
        {--password= : Use this instead of a generated one}';

    protected $description = 'Set an admin panel password (generates a strong one by default)';

    public function handle(): int
    {
        $admin = AdminUser::where('email', $this->argument('email'))->first();

        if (! $admin) {
            $this->error('No admin user with that email.');
            $this->line('Known: '.AdminUser::pluck('email')->implode(', '));

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(20, symbols: false);

        $admin->forceFill([
            'password' => Hash::make($password),
            // Any session or "remember me" cookie issued under the old password
            // keeps working otherwise, which defeats the point of rotating it.
            'remember_token' => Str::random(60),
        ])->save();

        $this->info("Password updated for {$admin->email}.");
        $this->line('  '.$password);
        $this->warn('Shown once — only the hash is stored. Save it now.');

        return self::SUCCESS;
    }
}
