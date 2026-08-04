<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Operational alerts (Telegram). Observers only queue work — the job
        // is afterCommit, so a rolled-back payment never announces itself.
        \App\Models\Deposit::observe(\App\Observers\DepositObserver::class);
        \App\Models\Transaction::observe(\App\Observers\TransactionObserver::class);

        //
    }
}
