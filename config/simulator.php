<?php

declare(strict_types=1);

/*
 * Bank API simulators.
 *
 * These exist so money code can be exercised end to end without a bank
 * sandbox. They must never be reachable in production: a misconfigured base
 * URL there would make the cabinet report transfers as successful when nothing
 * moved. Hence the default below, and the guard in the outbound driver.
 */
return [

    'aloqabank' => [

        // Off in production regardless of anything else set here.
        'enabled' => env('APP_ENV', 'production') !== 'production'
            && (bool) env('SIMULATOR_ALOQABANK_ENABLED', true),

        // How long a created order sits at "Введен" before it settles. Keep it
        // non-zero: an integration that only ever sees the terminal status is
        // not being tested against the bank's actual asynchronous behaviour.
        'settle_after_seconds' => (int) env('SIMULATOR_ALOQABANK_SETTLE_AFTER', 10),

        // How long the …9999 magic account hangs for, to trip a client timeout.
        'timeout_seconds' => (int) env('SIMULATOR_ALOQABANK_TIMEOUT', 30),

    ],

];
