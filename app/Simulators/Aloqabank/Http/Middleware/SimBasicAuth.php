<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Http\Middleware;

use App\Simulators\Aloqabank\Models\SimPartner;
use App\Simulators\Aloqabank\Support\BankResponse;
use App\Simulators\Aloqabank\Support\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Basic, as the bank specifies.
 *
 * Bad credentials return 1004 in a 200 envelope rather than a 401 — the bank
 * signals failure in the body, and a client that only checks the status line
 * would sail straight past it. Reproducing that is the point.
 */
class SimBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = SimPartner::where('username', (string) $request->getUser())
            ->where('is_active', true)
            ->first();

        // hash_equals: constant-time even here, so the simulator does not model
        // a timing leak the real bank presumably does not have.
        if (! $partner || ! hash_equals($partner->password, (string) $request->getPassword())) {
            return BankResponse::error(ErrorCode::USER_NOT_FOUND);
        }

        $request->attributes->set('sim_partner', $partner);

        return $next($request);
    }
}
