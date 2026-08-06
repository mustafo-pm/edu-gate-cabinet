<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends anyone holding a temporary password to change it before doing anything
 * else.
 *
 * Without this the flag is decoration: an admin issues a "temporary" password,
 * reads it down the phone, and it stays valid until someone remembers to
 * change it. The redirect is what makes it temporary.
 *
 * Deliberately NOT in the middleware priority list, so it keeps its place at
 * the end of the stack and runs after authentication — it needs a user before
 * it can ask anything about one.
 */
class RequirePasswordChange
{
    /** Reachable while a change is pending, or the redirect would loop. */
    private const ALLOWED = [
        'password/change',
        'merchant/logout',
        'partner/logout',
        'admin/logout',
        'livewire/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('merchant')
            ?? $request->user('psp')
            ?? $request->user('admin');

        if (! $user || ! ($user->must_change_password ?? false)) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED)) {
            return $next($request);
        }

        // An API or fetch call gets an answer it can act on rather than HTML.
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'error' => [
                    'code' => 'password_change_required',
                    'message' => 'A temporary password must be changed before continuing.',
                ],
            ], 423);
        }

        return redirect()->route('password.change');
    }
}
