<?php

declare(strict_types=1);

namespace App\Http\Controllers\Psp;

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
        return view('partner.auth.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('psp')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! Auth::guard('psp')->user()->is_active) {
            Auth::guard('psp')->logout();
            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Contact EduGate.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('psp.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('psp')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
