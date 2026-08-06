<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The root of api.edu-gate.uz.
 *
 * Deliberately JSON rather than the documentation site. A static docs page
 * served from the API's own hostname answers 200 even when the API itself is
 * down, so any partner monitoring the root would report healthy through an
 * outage. Documentation is linked from here instead of hosted here.
 *
 * An invokable controller rather than a closure: `route:cache` refuses to
 * serialize closure routes, and the deploy caches routes.
 */
class ApiIndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => [
                'service' => 'EduGate PSP API',
                'version' => 'v1',
                'base_path' => '/api/v1',
                'docs' => config('domains.docs'),
                'amounts' => 'integer tiyin (1 UZS = 100 tiyin)',
            ],
        ]);
    }
}
