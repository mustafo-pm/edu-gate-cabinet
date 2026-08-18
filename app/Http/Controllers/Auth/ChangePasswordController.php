<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MerchantUser;
use App\Models\PspUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * One change-password screen for all three cabinets.
 *
 * The guards are separate by design and never share a session, but the screen
 * they need here is identical, and three copies would be three places to get
 * the validation subtly wrong.
 */
class ChangePasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->user($request)) {
            return redirect()->route('login');
        }

        $user = $this->user($request);

        return view('auth.change-password', [
            'forced' => (bool) ($user->must_change_password ?? false),
            'logoutRoute' => $this->logoutRoute($user),
            // Shown on the form, and named `username` for the browser's
            // password manager. See the view for why that matters.
            'email' => (string) $user->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('cabinet.auth.wrong_current_password')]);
        }

        // Refusing the same value matters most on the forced path: reusing the
        // temporary password would clear the flag while leaving the credential
        // the admin read out still live.
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => __('cabinet.auth.password_must_differ')]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        return redirect($this->home($user))->with('status', __('cabinet.auth.password_updated'));
    }

    private function user(Request $request)
    {
        return $request->user('merchant')
            ?? $request->user('psp')
            ?? $request->user('admin');
    }

    /**
     * Signing out is the only other action offered while a change is forced,
     * and each guard has its own logout route — there is no shared one.
     */
    private function logoutRoute($user): string
    {
        return match (true) {
            $user instanceof MerchantUser => route('merchant.logout'),
            $user instanceof PspUser => route('psp.logout'),
            default => route('filament.admin.auth.logout'),
        };
    }

    /** Back to whichever cabinet this account belongs to. */
    private function home($user): string
    {
        return match (true) {
            $user instanceof MerchantUser => route('merchant.dashboard'),
            $user instanceof PspUser => route('psp.dashboard'),
            default => '/admin',
        };
    }
}
