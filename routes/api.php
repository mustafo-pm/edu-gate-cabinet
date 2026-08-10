<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PublicReceiptController;
use App\Http\Controllers\Api\PublicSiteController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

/*
 * Public, unauthenticated content for the marketing site. Kept outside /v1 so
 * it is never mistaken for part of the PSP contract. CORS for this prefix is
 * pinned to the website origins in config/cors.php.
 */
Route::prefix('public')->middleware('throttle:120,1')->group(function () {
    Route::get('site', [PublicSiteController::class, 'show'])->name('public.site');

    // Receipt lookup by its random code. The route pattern rejects anything
    // the wrong shape before the controller runs, so a flood of junk never
    // reaches the database.
    Route::get('receipt/{code}', [PublicReceiptController::class, 'show'])
        ->where('code', '[a-z2-9]{32}')
        ->name('public.receipt');
});

/*
 * PSP-facing API — api.edu-gate.uz. Server-to-server, Sanctum bearer tokens.
 * Amounts are in tiyin. Envelope: { "status": "ok"|"error", "data"|"error": {...} }.
 * Rate limit: 60 requests/min per PSP token (or IP for the login endpoint).
 */
Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    // Public: obtain a token from an API key.
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated (api guard = Sanctum token → Psp model).
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}/institutions', [CategoryController::class, 'institutions']);

        Route::post('payments/check', [PaymentController::class, 'check']);
        Route::post('payments/confirm', [PaymentController::class, 'confirm']);
        Route::get('payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');

        Route::get('reports/payments', [ReportController::class, 'payments']);
    });
});
