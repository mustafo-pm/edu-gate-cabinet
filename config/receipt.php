<?php

declare(strict_types=1);

/*
 * Payment receipts.
 */
return [

    /*
     * The host that appears in the QR code and in links we hand out.
     *
     * Separate from APP_URL because the receipt is shown to strangers — a
     * university clerk scanning a QR should land somewhere that looks like the
     * brand, not on a host called "cabinet". Point this at edu-gate.uz once a
     * rewrite forwards /chek/* to the application; until then the cabinet host
     * is the honest answer, because that is where the page actually is.
     */
    'base_url' => env('RECEIPT_BASE_URL', env('APP_URL')),

    /*
     * Public page limits, per IP.
     *
     * `lookups` is generous: a clerk checking a stack of receipts is normal.
     * `misses` is what actually matters — a real visitor follows a working
     * link and almost never hits a bad code, while someone guessing codes hits
     * nothing but bad ones. Counting misses separately catches that without
     * getting in a legitimate user's way.
     */
    'rate_limit' => [
        'lookups_per_minute' => (int) env('RECEIPT_LOOKUPS_PER_MINUTE', 30),
        'misses_per_hour' => (int) env('RECEIPT_MISSES_PER_HOUR', 10),
    ],

];
