<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily payment summary to Telegram.
// Runs hourly and the command itself decides whether this is the configured
// hour — the schedule must NOT query the database, because console routes are
// loaded before migrations have run (a fresh install would crash).
Schedule::command('edugate:daily-summary')
    ->hourly()
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping();
