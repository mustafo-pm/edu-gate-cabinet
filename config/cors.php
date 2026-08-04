<?php

declare(strict_types=1);

/*
 * CORS is only needed for the one endpoint a browser calls cross-origin: the
 * public showcase feed that edu-gate.uz fetches from cabinet.edu-gate.uz.
 *
 * /api/v1/* is intentionally NOT listed. That is the PSP server-to-server API —
 * it is called by backends, which do not enforce CORS, so advertising it to
 * browsers only widens the surface for no benefit.
 */
return [

    'paths' => ['api/public/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://edu-gate.uz,https://www.edu-gate.uz')),
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Public data — never send cookies or the session with it.
    'supports_credentials' => false,

];
