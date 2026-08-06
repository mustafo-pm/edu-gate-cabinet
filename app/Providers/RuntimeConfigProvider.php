<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\ServiceProvider;

/**
 * Folds admin-managed settings into the framework's config at boot.
 *
 * Precedence is deliberate: the database wins over .env, but ONLY where a
 * value has actually been set. That way a host can still be pinned in .env for
 * an environment nobody should be reconfiguring from a browser, while the
 * ordinary case — someone changing the SMTP password after the mailbox is
 * rotated — needs no deploy and no SSH session.
 *
 * Runs in boot() rather than register() because it reads the database, and the
 * connection is not resolvable during registration.
 */
class RuntimeConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyMail();
    }

    private function applyMail(): void
    {
        // Nothing configured yet: leave .env in charge, which on a fresh
        // install means MAIL_MAILER=log and mail quietly going nowhere.
        if (! Settings::has('mail_host')) {
            return;
        }

        $host = Settings::get('mail_host');
        $port = (int) Settings::get('mail_port', 587);

        // 'null' rather than null: Symfony's transport treats the string
        // 'null' as "no encryption", and the form offers it as an option.
        $encryption = Settings::get('mail_encryption', 'tls');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.username' => Settings::get('mail_username'),
            'mail.mailers.smtp.password' => Settings::get('mail_password'),
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => Settings::get('mail_from_address', config('mail.from.address')),
            'mail.from.name' => Settings::get('mail_from_name', config('mail.from.name')),
        ]);
    }
}
