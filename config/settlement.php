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

];
