<?php

use App\Http\Controllers\Auth\UnifiedLoginController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// One smart login for everyone — detects role and routes to the right cabinet.
Route::get('/', [UnifiedLoginController::class, 'show'])->name('login');
Route::post('/login', [UnifiedLoginController::class, 'attempt'])->name('login.attempt');

Route::get('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
