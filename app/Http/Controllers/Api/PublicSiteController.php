<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Showcase;
use Illuminate\Http\JsonResponse;

/**
 * Unauthenticated content feed for the marketing site (edu-gate.uz), which is
 * static and lives on a different host.
 *
 * Deliberately outside /api/v1 — that prefix is the PSP contract and carries
 * Sanctum auth. This is a read-only, public, cacheable resource with no tenant
 * or financial data in it whatsoever.
 */
class PublicSiteController extends Controller
{
    public function show(): JsonResponse
    {
        return response()
            ->json(['status' => 'ok', 'data' => Showcase::payload()])
            // Lets a CDN or the browser absorb repeat views; matches the
            // server-side cache TTL so a published edit appears promptly.
            ->header('Cache-Control', 'public, max-age=300');
    }
}
