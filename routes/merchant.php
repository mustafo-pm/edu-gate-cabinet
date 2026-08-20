<?php

declare(strict_types=1);

use App\Http\Controllers\Merchant\AuthController;
use App\Livewire\Merchant\Analytics;
use App\Livewire\Merchant\BankAccounts;
use App\Livewire\Merchant\Dashboard;
use App\Livewire\Merchant\Departments;
use App\Livewire\Merchant\Messaging;
use App\Livewire\Merchant\Payments;
use App\Livewire\Merchant\Profile;
use App\Livewire\Merchant\Reports;
use App\Livewire\Merchant\Schedules;
use App\Livewire\Merchant\Staff;
use App\Livewire\Merchant\Students;
use App\Support\CabinetRoles;
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

    // The dashboard is the one page everybody keeps: an account with a role
    // but no matching screen would land on a 403 immediately after signing in.
    Route::get('/', Dashboard::class)->name('dashboard');

    /*
     * Gated on permissions, not on roles. A role is a bundle somebody may
     * retune; the screens care about the capability itself.
     *
     * Note these are only meaningful once `edugate:roles` has run — before
     * that nobody holds anything and every one of these 403s.
     */
    Route::get('students', Students::class)->name('students')
        ->middleware('can:'.CabinetRoles::STUDENTS_VIEW);
    Route::get('schedules', Schedules::class)->name('schedules')
        ->middleware('can:'.CabinetRoles::SCHEDULES);
    Route::get('payments', Payments::class)->name('transactions')
        ->middleware('can:'.CabinetRoles::PAYMENTS_VIEW);

    // The institution's own profile and where it is paid.
    Route::get('profile', Profile::class)->name('profile')
        ->middleware('can:'.CabinetRoles::PROFILE);

    // Where money lands. The narrowest permission in the cabinet, held by the
    // owner alone unless somebody deliberately widens it.
    Route::get('bank-accounts', BankAccounts::class)->name('bank-accounts')
        ->middleware('can:'.CabinetRoles::BANK_ACCOUNTS);

    Route::get('accounts', Staff::class)->name('accounts')
        ->middleware('can:'.CabinetRoles::STAFF);

    // Faculties and chairs — the grouping students and reporting hang off.
    Route::get('departments', Departments::class)->name('departments')
        ->middleware('can:'.CabinetRoles::STUDENTS_MANAGE);

    // Demo pages (UI only)
    Route::get('analytics', Analytics::class)->name('analytics');
    Route::get('reports', Reports::class)->name('reports')
        ->middleware('can:'.CabinetRoles::REPORTS);
    Route::get('messaging', Messaging::class)->name('messaging');
});
