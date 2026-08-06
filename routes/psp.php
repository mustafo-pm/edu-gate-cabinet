<?php

declare(strict_types=1);

use App\Http\Controllers\Psp\AuthController;
use App\Livewire\Psp\ApiKeys;
use App\Livewire\Psp\Dashboard;
use App\Livewire\Psp\Deposits;
use App\Livewire\Psp\Transactions;
use Illuminate\Support\Facades\Route;

/*
 * PSP / Partner cabinet — partner.edu-gate.uz (dev: /partner/*).
 * Registered with prefix "partner" and name "psp." in bootstrap/app.php.
 */

Route::middleware('guest:psp')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'attempt'])->name('login.attempt');
});

Route::middleware(['auth:psp', 'password.change'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('deposits', Deposits::class)->name('deposits');
    Route::get('transactions', Transactions::class)->name('transactions');
    Route::get('api-keys', ApiKeys::class)->name('api-keys');
});
