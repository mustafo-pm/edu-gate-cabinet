<?php

declare(strict_types=1);

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

/*
 * Public payment receipt — reached by scanning a QR code, no login.
 *
 * In its own file because it is registered on TWO hosts: the cabinet, where the
 * application actually lives, and the marketing domain, which is where the link
 * handed to a payer should point. A university clerk checking a receipt should
 * land on edu-gate.uz, not on a host called "cabinet".
 *
 * The code is random rather than the payment id, so the page cannot be walked
 * from 1 upwards; see PaymentReceipt. Rate limiting lives in the controller
 * because a wrong code has to be counted differently from a right one.
 */
Route::get('chek/{code}', [ReceiptController::class, 'show'])->name('receipt.show');
Route::get('chek/{code}/pdf', [ReceiptController::class, 'pdf'])->name('receipt.pdf');
