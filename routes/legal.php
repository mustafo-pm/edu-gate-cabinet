<?php

declare(strict_types=1);

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

/*
 * Public legal documents — the offer, the privacy policy and their siblings.
 *
 * In its own file, like routes/receipt.php, because it is registered on the
 * cabinet host and on the marketing host once one is configured: a link to the
 * offer belongs on edu-gate.uz, not on a host called "cabinet".
 *
 * Unlike the receipt these pages SHOULD be indexed by search engines — people
 * look for "edu-gate oferta" — so there is no noindex here and no throttle.
 */
Route::get('hujjat/{slug}', [LegalController::class, 'show'])
    ->where('slug', '[a-z0-9-]{2,60}')
    ->name('legal.show');
