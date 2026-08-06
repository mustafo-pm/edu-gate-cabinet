<?php

declare(strict_types=1);

/*
 * Settling collected tuition out to institutions.
 */
return [

    /*
     * Queue a bank transfer as soon as a payment is confirmed.
     *
     * On by default because the product promises settlement in seconds, which
     * only holds if confirming a payment immediately queues the transfer.
     * Turn it off to record postings without sending them — useful while a new
     * bank integration is being verified.
     */
    'auto' => (bool) env('SETTLEMENT_AUTO', true),

    /*
     * Aloqabank answers "Введен" and settles later, so something has to ask
     * again. This is how long a posting may sit in `sent` before the poller
     * stops chasing it and leaves it for a human.
     */
    'poll_for_hours' => (int) env('SETTLEMENT_POLL_HOURS', 24),

    /*
     * Payments confirmed BEFORE this moment are never auto-settled.
     *
     * Without it, the first deploy of settlement onto a system with history
     * would have the backstop discover every past payment "missing a posting"
     * and try to pay them all — money that was very likely already transferred
     * by hand. Set it to the moment settlement goes live on each environment.
     */
    'start_at' => env('SETTLEMENT_START_AT'),

];
