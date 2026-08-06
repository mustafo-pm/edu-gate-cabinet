<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group to one hostname.
 *
 * Route::domain() covers most groups, but a Filament panel registers its own
 * routes and exposes no domain option, so the panel is pinned with middleware
 * instead.
 *
 * Answers 404, not 403: a wrong host should look like nothing is there rather
 * than confirm that an admin panel exists and is merely served elsewhere.
 * Skipped entirely when the matching config key is unset, which is how
 * development keeps everything on one host.
 */
class EnforceHost
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        $expected = config("domains.{$key}");

        if (filled($expected) && $request->getHost() !== $expected) {
            abort(404);
        }

        return $next($request);
    }
}
