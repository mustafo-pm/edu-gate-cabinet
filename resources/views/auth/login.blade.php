<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('cabinet.auth.sign_in') }} · EduGate</title>
    <x-eg.head />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-eg-surface font-sans text-eg-ink antialiased">
<div class="grid min-h-screen lg:grid-cols-2">
    {{-- Brand panel --}}
    <div class="relative hidden flex-col justify-between p-12 text-white lg:flex" style="background:var(--eg-grad-cta)">
        <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="h-8 w-auto">
        <div>
            <h2 class="text-3xl font-extrabold leading-tight">{{ __('cabinet.landing.hero_title') }}</h2>
            <p class="mt-3 max-w-sm text-white/70">{{ __('cabinet.landing.hero_sub') }}</p>
        </div>
        <p class="text-sm text-white/50">edu-gate.uz</p>
    </div>

    {{-- Form --}}
    <div class="relative flex items-center justify-center p-6">
        <div class="absolute right-4 top-4"><x-eg.controls /></div>
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden">
                <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="h-7 w-auto dark:hidden">
                <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="hidden h-7 w-auto dark:block">
            </div>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('cabinet.auth.sign_in') }}</h1>
            <p class="mt-1 text-sm text-eg-muted">{{ __('cabinet.auth.welcome_back') }}</p>

            @if ($errors->any())
                <div class="mt-4 rounded-md border border-eg-danger/30 bg-eg-danger/10 px-4 py-3 text-sm text-eg-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="email">{{ __('cabinet.auth.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="eg-input" placeholder="you@edu-gate.uz">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="password">{{ __('cabinet.auth.password') }}</label>
                    <input id="password" name="password" type="password" required class="eg-input" placeholder="••••••••">
                </div>
                <label class="flex items-center gap-2 text-sm text-eg-text">
                    <input type="checkbox" name="remember" class="rounded border-eg-border"> {{ __('cabinet.auth.remember_me') }}
                </label>
                <button type="submit" class="eg-btn eg-btn--primary w-full">{{ __('cabinet.auth.sign_in') }}</button>
            </form>

            <p class="mt-6 text-center text-xs text-eg-muted">
                merchant@ · psp@ · admin@edu-gate.uz — password
            </p>
        </div>
    </div>
</div>
</body>
</html>
