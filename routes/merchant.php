<?php

declare(strict_types=1);

use App\Http\Controllers\Merchant\AuthController;
use App\Livewire\Merchant\Dashboard;
use App\Livewire\Merchant\Payments;
use App\Livewire\Merchant\Schedules;
use App\Livewire\Merchant\Students;
use Illuminate\Support\Facades\Route;

/*
 * Merchant cabinet — app.edu-gate.uz (dev: /merchant/*).
 * Registered with prefix "merchant" and name "merchant." in bootstrap/app.php.
 */

// Guest
Route::middleware('guest:merchant')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'attempt'])->name('login.attempt');
});

// Authenticated
Route::middleware('auth:merchant')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('students', Students::class)->name('students');
    Route::get('schedules', Schedules::class)->name('schedules');
    Route::get('payments', Payments::class)->name('transactions');

    // Demo pages (UI only)
    Route::get('analytics', \App\Livewire\Merchant\Analytics::class)->name('analytics');
    Route::get('departments', \App\Livewire\Merchant\Departments::class)->name('departments');
    Route::get('reports', \App\Livewire\Merchant\Reports::class)->name('reports');
    Route::get('messaging', \App\Livewire\Merchant\Messaging::class)->name('messaging');
    Route::get('profile', \App\Livewire\Merchant\Profile::class)->name('profile');
    Route::get('accounts', \App\Livewire\Merchant\Accounts::class)->name('accounts');
});
