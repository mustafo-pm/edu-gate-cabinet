<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * The address to show a person, as opposed to the one we route on.
 *
 * The cabinets were once meant to live at app./partner./admin.edu-gate.uz and
 * several screens still said so long after the deployment settled on a single
 * cabinet host with path prefixes. Printed on a sign-in page that is the worst
 * kind of wrong: it reads as authoritative and sends people to a name that does
 * not resolve.
 *
 * So the label is derived rather than typed. In development nothing is pinned
 * and we fall back to whatever host the request came in on, which is the honest
 * answer there too.
 */
final class Hosts
{
    /** Bare hostname of the cabinet, e.g. "cabinet.edu-gate.uz". */
    public static function cabinet(): string
    {
        return (string) (config('domains.cabinet') ?: Request::getHost());
    }

    /** Cabinet host with a section's path, e.g. "cabinet.edu-gate.uz/merchant". */
    public static function section(string $path): string
    {
        return self::cabinet().'/'.ltrim($path, '/');
    }
}
