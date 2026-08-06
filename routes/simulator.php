<?php

declare(strict_types=1);

use App\Simulators\Aloqabank\Http\Controllers\AccountController;
use App\Simulators\Aloqabank\Http\Controllers\PaymentController;
use App\Simulators\Aloqabank\Http\Controllers\ServiceController;
use App\Simulators\Aloqabank\Http\Middleware\SimBasicAuth;
use Illuminate\Support\Facades\Route;

/*
 * Aloqabank API simulator.
 *
 * Not part of EduGate's API — this stands in for the BANK while we have no
 * access to their sandbox, so the cabinet can make genuine HTTP calls (Basic
 * auth, real client, real timeouts) against something that behaves like them.
 * The point is that the cabinet's code path is identical in dev and in prod;
 * only the base URL changes.
 *
 * Registered from bootstrap/app.php only when config('simulator.aloqabank.enabled')
 * is true, which defaults to false in production.
 */
Route::prefix('sim/aloqabank/api/v2')
    ->middleware(SimBasicAuth::class)
    ->group(function () {
        Route::get('services', [ServiceController::class, 'index']);

        Route::post('payment', [PaymentController::class, 'payment']);
        Route::post('paymentBudget', [PaymentController::class, 'paymentBudget']);
        Route::get('payment/{orderId}', [PaymentController::class, 'status']);

        Route::get('account/{serviceId}/balance', [AccountController::class, 'balance']);
        Route::post('account/payments', [AccountController::class, 'payments']);
    });
