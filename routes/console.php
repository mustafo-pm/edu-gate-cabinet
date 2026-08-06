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

// Banks accept a payment order and settle it later, so postings sitting at
// `sent` have to be chased or the register never learns whether the money
// arrived. Frequent because the product promises settlement in seconds.
Schedule::command('transfers:poll')
    ->everyMinute()
    ->withoutOverlapping();

// Backstop for a queue worker that was down when a payment was confirmed: the
// settlement job would have waited, or been lost with the queue, and nothing
// else would ever notice the institution had not been paid.
Schedule::command('transfers:settle-missing')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Shared hosting has no process supervisor — cPanel's Application Manager runs
// Passenger web apps, not background workers — so the scheduler drains the
// queue itself. runInBackground keeps a slow job from delaying transfers:poll
// in the same tick, and max-time bounds each run so ticks cannot pile up.
// Turn this OFF wherever a real supervisor (Horizon, systemd) runs a worker,
// or the two will compete for the same jobs.
if (config('queue.drain_from_scheduler')) {
    Schedule::command('queue:work', ['--stop-when-empty', '--tries=3', '--max-time=50'])
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}
