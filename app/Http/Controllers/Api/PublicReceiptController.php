<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceiptResource;
use App\Support\ReceiptLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One GET: hand it a receipt code, get the payment back as JSON.
 *
 * This is what the marketing site calls to render a receipt without the
 * cabinet having to serve the page itself. Unauthenticated by design — the
 * link is the credential — which is why the parameter is the receipt's random
 * code and not the payment id. A sequential parameter would turn this into a
 * way to walk from 1 upwards and collect every student's name, institution and
 * amount. Guarded identically to the HTML page via ReceiptLookup.
 *
 * Deliberately outside /api/v1: that prefix is the PSP contract and carries
 * Sanctum auth. A PSP asking about its own payment uses GET /api/v1/payments/{id}.
 */
class PublicReceiptController extends Controller
{
    public function show(Request $request, string $code): JsonResponse
    {
        if (ReceiptLookup::throttled($request)) {
            return response()->json([
                'status' => 'error',
                'error' => ['code' => 'too_many_requests', 'message' => __('receipt.throttled_body')],
            ], 429);
        }

        $receipt = ReceiptLookup::find($request, $code);

        if (! $receipt) {
            // The same answer whether the code is malformed, unknown or merely
            // wrong — nothing here tells a guesser they are getting warmer.
            return response()->json([
                'status' => 'error',
                'error' => ['code' => 'not_found', 'message' => __('receipt.not_found_title')],
            ], 404);
        }

        return response()
            ->json(['status' => 'ok', 'data' => new ReceiptResource($receipt)])
            // Never cached. The whole point is that a refund shows up here
            // immediately, even though the paper still says paid.
            ->header('Cache-Control', 'no-store');
    }
}
