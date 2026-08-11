<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\UnifiedLoginController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// One smart login for everyone — detects role and routes to the right cabinet.
Route::get('/', [UnifiedLoginController::class, 'show'])->name('login');
Route::post('/login', [UnifiedLoginController::class, 'attempt'])->name('login.attempt');

Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

/*
 * One change-password screen for all three cabinets. The guards never share a
 * session, but this screen is identical for each, and three copies would be
 * three chances to get the validation subtly wrong.
 */
Route::get('password/change', [ChangePasswordController::class, 'show'])->name('password.change');
Route::post('password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');

// The public receipt lives in routes/receipt.php — it is registered on the
// marketing host as well as this one, so it cannot sit inside this file.
