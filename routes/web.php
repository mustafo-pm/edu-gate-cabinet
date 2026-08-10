<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\ReceiptController;
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

/*
 * Public payment receipt — reached by scanning a QR code, no login.
 *
 * The code is random rather than the payment id, so the page cannot be walked
 * from 1 upwards; see PaymentReceipt. Rate limiting lives in the controller
 * because a wrong code has to be counted differently from a right one.
 */
Route::get('chek/{code}', [ReceiptController::class, 'show'])->name('receipt.show');
Route::get('chek/{code}/pdf', [ReceiptController::class, 'pdf'])->name('receipt.pdf');
