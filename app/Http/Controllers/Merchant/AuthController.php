<?php

declare(strict_types=1);

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('merchant.auth.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('merchant')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! Auth::guard('merchant')->user()->is_active) {
            Auth::guard('merchant')->logout();
            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Contact your administrator.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('merchant.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('merchant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
