<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Finding a receipt from an untrusted code.
 *
 * Shared by the HTML page and the JSON endpoint on purpose. Both are open to
 * the internet with no login in front of them, so they have to be guarded
 * identically — and two copies of this logic would drift the moment one of
 * them is touched, leaving the softer door as the way in.
 */
final class ReceiptLookup
{
    /**
     * Whether this caller must be turned away before we look anything up.
     *
     * Counts the attempt when it lets the caller through, so a caller who is
     * refused does not also pay for the refusal.
     */
    public static function throttled(Request $request): bool
    {
        $limits = config('receipt.rate_limit');

        // Misses first: someone guessing codes trips this long before they
        // reach the ordinary per-minute ceiling.
        if (RateLimiter::tooManyAttempts(self::missKey($request), $limits['misses_per_hour'])) {
            return true;
        }

        if (RateLimiter::tooManyAttempts(self::lookupKey($request), $limits['lookups_per_minute'])) {
            return true;
        }

        RateLimiter::hit(self::lookupKey($request), 60);

        return false;
    }

    public static function find(Request $request, string $code): ?PaymentReceipt
    {
        // Cheap shape check first: a wrong-length code never touches the
        // database, so flooding the endpoint costs an attacker more than us.
        $receipt = self::wellFormed($code)
            ? PaymentReceipt::with('transaction')->where('code', $code)->first()
            : null;

        if (! $receipt) {
            // A real visitor follows a working link and never lands here; a
            // guesser produces nothing but misses. Counting the two separately
            // catches them without slowing anybody else down.
            RateLimiter::hit(self::missKey($request), 3600);
        }

        return $receipt;
    }

    public static function wellFormed(string $code): bool
    {
        return preg_match('/^[a-z2-9]{32}$/', $code) === 1;
    }

    private static function lookupKey(Request $request): string
    {
        return 'receipt:look:'.$request->ip();
    }

    private static function missKey(Request $request): string
    {
        return 'receipt:miss:'.$request->ip();
    }
}
