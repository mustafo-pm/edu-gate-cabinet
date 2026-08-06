<?php

declare(strict_types=1);

/*
 * Which hostname serves which part of the platform.
 *
 * All null by default, which means "serve everything on every host" — the
 * behaviour in development, where the parts are told apart by path prefix
 * (/merchant, /partner, /admin) rather than by subdomain.
 *
 * Set them in production and each group answers only on its own host. That
 * matters because the cabinet and the API are the same Laravel application: if
 * api.edu-gate.uz simply points at the same public/ directory, then without
 * these the Filament admin panel and both cabinets become reachable there too.
 *
 * NOTE: setting API_DOMAIN means the API stops answering on the cabinet host.
 * That is the point, but any Postman environment or partner still calling
 * cabinet.edu-gate.uz/api/v1/... must be repointed at the same time.
 */
return [

    // Merchant + partner cabinets and the unified login.
    'cabinet' => env('CABINET_DOMAIN'),

    // The PSP API. Nothing else should answer here.
    'api' => env('API_DOMAIN'),

    // The Filament admin panel; falls back to the cabinet host.
    'admin' => env('ADMIN_DOMAIN', env('CABINET_DOMAIN')),

    // Where partner-facing documentation lives. Only used to point callers at
    // it from the API root.
    'docs' => env('DOCS_URL', 'https://docs.edu-gate.uz'),

];
