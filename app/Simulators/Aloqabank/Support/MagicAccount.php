<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Support;

use App\Simulators\Aloqabank\Models\SimPayment;

/**
 * Failure injection driven by the receiver account number.
 *
 * A simulator that only ever succeeds is worse than none: it teaches the code
 * that the happy path is the only path. Rather than keep mutable "next call
 * fails" state, the outcome is encoded in the LAST FOUR DIGITS of
 * receiverAccount — the same trick card processors use with test card numbers.
 * It is deterministic, needs no setup, and a failing test names its own cause.
 *
 * The two that matter most:
 *   …9999 — the request hangs past any sane client timeout. This is the
 *           dangerous case: did the money move or not? The bank's own guidance
 *           (query by orderId, never blind-retry) exists for exactly this.
 *   …7777 — accepted and then stuck at "Введен" forever. Polling must give up
 *           eventually instead of looping until the end of time.
 */
class MagicAccount
{
    /** Reject the request outright with this code. */
    public const IMMEDIATE_ERRORS = [
        '0013' => ErrorCode::ACCOUNT_NOT_FOUND,
        '0014' => ErrorCode::BANK_NOT_IN_SMP,
        '1017' => ErrorCode::MISSING_REQUIRED_FIELD,
        '3333' => ErrorCode::DOC_DATE_BEFORE_OPERATING_DAY,
        '1111' => ErrorCode::SYSTEM_ERROR,
        '2222' => ErrorCode::CRITICAL_ERROR,
        '1008' => ErrorCode::FETCH_FAILED,
    ];

    /** Transport-level nastiness, before any envelope is produced. */
    public const TIMEOUT = '9999';

    public const MALFORMED_JSON = '8888';

    /** Accepted, but never settles. */
    public const STUCK = '7777';

    /** Accepted, then rejected by the core banking system. */
    public const REJECTED = '6666';

    public static function suffix(string $account): string
    {
        return substr($account, -4);
    }

    /** The error code this account forces, or null to proceed normally. */
    public static function immediateError(string $account): ?int
    {
        return self::IMMEDIATE_ERRORS[self::suffix($account)] ?? null;
    }

    public static function isTimeout(string $account): bool
    {
        return self::suffix($account) === self::TIMEOUT;
    }

    public static function isMalformed(string $account): bool
    {
        return self::suffix($account) === self::MALFORMED_JSON;
    }

    /** What this payment will eventually settle to once accepted. */
    public static function settlesTo(string $account): string
    {
        return match (self::suffix($account)) {
            self::STUCK => SimPayment::ENTERED,
            self::REJECTED => SimPayment::DELETED,
            default => SimPayment::EXECUTED,
        };
    }

    /** @return array<string, string> For docs and the admin screen. */
    public static function legend(): array
    {
        return [
            '…0013' => '1013 — account not found',
            '…0014' => '1014 — receiver bank not an SMP member',
            '…1017' => '1017 — missing required fields',
            '…3333' => '3333 — document date before the operating day',
            '…1111' => '1111 — system error (query status, do NOT retry)',
            '…2222' => '2222 — critical error (query status, do NOT retry)',
            '…1008' => '1008 — could not fetch data (retry creation)',
            '…9999' => 'request hangs past the client timeout',
            '…8888' => 'HTTP 200 with a malformed body',
            '…7777' => 'accepted, then stuck at "Введен" forever',
            '…6666' => 'accepted, then rejected to "Удален"',
        ];
    }
}
