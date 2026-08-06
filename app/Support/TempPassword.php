<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AlertEvent;
use App\Models\AdminUser;
use App\Models\MerchantUser;
use App\Models\PspUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Issues temporary passwords for the three cabinet account types.
 *
 * The plaintext is returned to the caller and never stored, logged, or put in
 * a Telegram message: the alert says an account was created or reset, and the
 * password is shown once on the admin screen for whoever will hand it over.
 * A team chat keeps its history forever, so a credential posted there outlives
 * the account it belongs to.
 *
 * Every issued password is marked `must_change_password`, which is the whole
 * difference between a temporary password and simply a password someone else
 * knows.
 */
final class TempPassword
{
    /**
     * Symbols are excluded deliberately. These get read down a phone or typed
     * from a note, and the cost of an ambiguous character is a support call —
     * length carries the entropy instead.
     */
    public static function generate(int $length = 14): string
    {
        return Str::password($length, symbols: false);
    }

    /**
     * Replace an account's password with a fresh temporary one.
     *
     * Returns the plaintext exactly once. Rotating `remember_token` matters as
     * much as the password: a live "remember me" cookie would otherwise keep
     * the old holder signed in, which is precisely what a reset is meant to
     * stop.
     */
    public static function issue(Model $user, ?string $by = null, bool $isNew = false): string
    {
        $password = self::generate();

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
            'password_changed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        Alerts::raise($isNew ? AlertEvent::UserCreated : AlertEvent::PasswordReset, [
            'name' => $user->name,
            'email' => $user->email,
            'cabinet' => self::cabinet($user),
            'organisation' => self::organisation($user),
            'by' => $by,
        ]);

        return $password;
    }

    /** Which cabinet this account signs in to — shown in the alert. */
    public static function cabinet(Model $user): string
    {
        return match (true) {
            $user instanceof MerchantUser => 'Institution (app)',
            $user instanceof PspUser => 'Partner / PSP',
            $user instanceof AdminUser => 'EduGate admin',
            default => class_basename($user),
        };
    }

    /** The tenant an account belongs to, where it has one. */
    public static function organisation(Model $user): ?string
    {
        return match (true) {
            $user instanceof MerchantUser => $user->merchant?->name,
            $user instanceof PspUser => $user->psp?->name,
            default => null,
        };
    }
}
