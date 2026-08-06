<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Support;

use Illuminate\Http\JsonResponse;

/**
 * The bank's response envelopes, reproduced exactly — inconsistencies included.
 *
 * Two quirks are deliberate, not oversights:
 *   • /payment and /payment/{orderId} carry a `code`; /balance and
 *     /account/payments do NOT. The docs show them that way.
 *   • `balance` comes back as a STRING ("1100") while statement `amount` is an
 *     integer (400).
 *
 * Smoothing these over would defeat the purpose: our client has to survive the
 * real thing, and this is the shape of the real thing. Everything is HTTP 200 —
 * the bank signals failure in `status`/`code`, not the status line.
 */
class BankResponse
{
    public static function success(array|string|null $data = null): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'code' => 0,
            'data' => $data,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** For /balance and /account/payments, which omit `code` entirely. */
    public static function plain(array|string|null $data = null): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public static function error(int $code, ?string $message = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => $code,
            'message' => $message ?? ErrorCode::message($code),
            'data' => null,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
