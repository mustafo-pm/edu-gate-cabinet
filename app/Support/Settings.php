<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Configuration the admin panel can change without a deploy.
 *
 * Read on every request, so the whole set is cached as one array rather than
 * queried per key.
 *
 * Everything here tolerates the table not existing. That is not defensive
 * padding: this is consulted while the framework boots, which happens during
 * `migrate` on a fresh install and during every artisan command in CI before
 * any table exists. A hard failure there bricks the install with an error that
 * points nowhere near the cause — the same trap that once made a database
 * query in routes/console.php crash every command on a clean checkout.
 */
final class Settings
{
    public const CACHE_KEY = 'settings.all';

    /** Encrypted at rest, and never returned to a form or an audit record. */
    public const SECRET_KEYS = ['mail_password'];

    /** @return array<string, string|null> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! self::tableExists()) {
                return [];
            }

            return Setting::all()->mapWithKeys(fn (Setting $s) => [
                $s->key => self::decode($s),
            ])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function has(string $key): bool
    {
        return filled(self::all()[$key] ?? null);
    }

    /** @param  array<string, mixed>  $values */
    public static function put(array $values): void
    {
        if (! self::tableExists()) {
            return;
        }

        foreach ($values as $key => $value) {
            $secret = in_array($key, self::SECRET_KEYS, true);

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value === null ? null : ($secret ? Crypt::encryptString((string) $value) : (string) $value),
                    'is_encrypted' => $secret,
                ],
            );
        }

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * A secret that cannot be decrypted is treated as absent rather than
     * fatal: APP_KEY rotation would otherwise take the whole application down
     * on boot instead of just breaking outgoing mail.
     */
    private static function decode(Setting $setting): ?string
    {
        if (! $setting->is_encrypted || $setting->value === null) {
            return $setting->value;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function tableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            // No database configured or reachable at all — during `key:generate`
            // on a fresh checkout, for instance.
            return false;
        }
    }
}
