<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\MerchantUser;
use App\Models\PspUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * One login page for every role. We detect which kind of account the email
 * belongs to, sign the user in on that guard, and route them to the matching
 * cabinet — so nobody has to pick "merchant / partner / admin" first.
 *
 * If an email somehow exists in more than one table, this priority wins:
 * admin > merchant > psp.
 */
class UnifiedLoginController extends Controller
{
    /** guard => [model, where to land after login] */
    private const GUARDS = [
        'admin' => AdminUser::class,
        'merchant' => MerchantUser::class,
        'psp' => PspUser::class,
    ];

    public function show(Request $request): View|RedirectResponse
    {
        // Already signed in on some guard? Skip the login page.
        foreach (array_keys(self::GUARDS) as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect($this->home($guard));
            }
        }

        return view('auth.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $guard = $this->detectGuard($credentials['email']);

        if ($guard === null || ! Auth::guard($guard)->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('cabinet.auth.bad_credentials'),
            ]);
        }

        if (! Auth::guard($guard)->user()->is_active) {
            Auth::guard($guard)->logout();
            throw ValidationException::withMessages([
                'email' => __('cabinet.auth.inactive'),
            ]);
        }

        $request->session()->regenerate();

        return redirect($this->home($guard));
    }

    /** Which guard owns this email (by lookup, honouring priority order). */
    private function detectGuard(string $email): ?string
    {
        foreach (self::GUARDS as $guard => $model) {
            if ($model::where('email', $email)->exists()) {
                return $guard;
            }
        }

        return null;
    }

    private function home(string $guard): string
    {
        return match ($guard) {
            'admin' => url('/admin'),
            'psp' => route('psp.dashboard'),
            default => route('merchant.dashboard'),
        };
    }
}
