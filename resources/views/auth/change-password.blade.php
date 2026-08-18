<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('cabinet.auth.change_password') }} · EduGate</title>
    <x-eg.head />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-eg-surface font-sans text-eg-ink antialiased">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden flex-col justify-between p-12 text-white lg:flex" style="background:var(--eg-grad-cta)">
        <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="h-8 w-auto">
        <div>
            <h2 class="text-3xl font-extrabold leading-tight">{{ __('cabinet.auth.change_password') }}</h2>
            <p class="mt-3 max-w-sm text-white/70">
                {{ $forced ? __('cabinet.auth.forced_intro') : __('cabinet.auth.voluntary_intro') }}
            </p>
        </div>
        <p class="text-sm text-white/50">edu-gate.uz</p>
    </div>

    <div class="relative flex items-center justify-center p-6">
        <div class="absolute right-4 top-4"><x-eg.controls /></div>
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden">
                <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="h-7 w-auto dark:hidden">
                <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="hidden h-7 w-auto dark:block">
            </div>

            <h1 class="text-2xl font-bold tracking-tight">{{ __('cabinet.auth.change_password') }}</h1>
            <p class="mt-1 text-sm text-eg-muted">
                {{ $forced ? __('cabinet.auth.forced_intro') : __('cabinet.auth.voluntary_intro') }}
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-md border border-eg-danger/30 bg-eg-danger/10 px-4 py-3 text-sm text-eg-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-4">
                @csrf

                {{-- Which account this is. Two jobs, both real.

                     For the person: all three cabinets share one host and one
                     sign-in page, so "change your password" on its own does not
                     say whose.

                     For the browser: a password manager keys saved credentials
                     on origin + username. With no username field it has to
                     guess which entry to update, and it picks whichever it last
                     used on this host — so changing the merchant's password
                     silently overwrites the saved admin one, and both are then
                     wrong. Not hypothetical; it happened. --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="username">
                        {{ __('cabinet.auth.account') }}
                    </label>
                    <input id="username" name="username" type="text" value="{{ $email }}"
                           autocomplete="username" readonly tabindex="-1"
                           class="eg-input cursor-default bg-eg-surface-2 text-eg-muted">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="current_password">
                        {{ __('cabinet.auth.current_password') }}
                    </label>
                    <input id="current_password" name="current_password" type="password" required autofocus
                           autocomplete="current-password" class="eg-input" placeholder="••••••••">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="password">
                        {{ __('cabinet.auth.new_password') }}
                    </label>
                    <input id="password" name="password" type="password" required
                           autocomplete="new-password" class="eg-input" placeholder="••••••••">
                    <p class="mt-1 text-xs text-eg-muted">{{ __('cabinet.auth.password_rules') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="password_confirmation">
                        {{ __('cabinet.auth.confirm_password') }}
                    </label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password" class="eg-input" placeholder="••••••••">
                </div>

                <button type="submit" class="eg-btn eg-btn--primary w-full">
                    {{ __('cabinet.auth.change_password') }}
                </button>
            </form>

            {{-- No way past this screen while a temporary password is in force,
                 so the only other action offered is signing out. --}}
            @if ($forced)
                <form method="POST" action="{{ $logoutRoute }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full text-center text-xs text-eg-muted hover:text-eg-ink">
                        {{ __('cabinet.auth.sign_out') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
</body>
</html>
