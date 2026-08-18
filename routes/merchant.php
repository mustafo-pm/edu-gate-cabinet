<?php

declare(strict_types=1);

use App\Http\Controllers\Merchant\AuthController;
use App\Livewire\Merchant\Accounts;
use App\Livewire\Merchant\Analytics;
use App\Livewire\Merchant\BankAccounts;
use App\Livewire\Merchant\Dashboard;
use App\Livewire\Merchant\Departments;
use App\Livewire\Merchant\Messaging;
use App\Livewire\Merchant\Payments;
use App\Livewire\Merchant\Profile;
use App\Livewire\Merchant\Reports;
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
Route::middleware(['auth:merchant', 'password.change'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('students', Students::class)->name('students');
    Route::get('schedules', Schedules::class)->name('schedules');
    Route::get('payments', Payments::class)->name('transactions');

    // The institution's own profile and where it is paid.
    Route::get('profile', Profile::class)->name('profile');
    Route::get('bank-accounts', BankAccounts::class)->name('bank-accounts');

    // Demo pages (UI only)
    Route::get('analytics', Analytics::class)->name('analytics');
    Route::get('departments', Departments::class)->name('departments');
    Route::get('reports', Reports::class)->name('reports');
    Route::get('messaging', Messaging::class)->name('messaging');
    Route::get('accounts', Accounts::class)->name('accounts');
});
