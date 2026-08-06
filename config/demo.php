<?php

declare(strict_types=1);

/*
 * Demonstration affordances.
 */
return [

    /*
     * Print the seeded logins on the sign-in pages.
     *
     * Deliberately NOT keyed on APP_ENV. The staging cabinet runs with
     * APP_ENV=local so the bank simulator's routes register, and an
     * environment-based check therefore published the credential hint on a
     * publicly reachable sign-in page. Whether a host is public and whether it
     * is "local" are different questions, so this is its own explicit switch,
     * off unless someone turns it on.
     */
    'show_credentials' => (bool) env('DEMO_SHOW_CREDENTIALS', false),

];
