<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EduGate — {{ __('cabinet.landing.hero_title') }}</title>
    <x-eg.head />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen font-sans text-white antialiased" style="background:var(--eg-grad-cta)">
<div class="mx-auto flex min-h-screen max-w-5xl flex-col px-6">
    <header class="flex items-center justify-between py-8">
        <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="h-8 w-auto">
        <x-eg.controls :onDark="true" />
    </header>

    <main class="flex flex-1 flex-col justify-center py-10">
        <div class="max-w-2xl">
            <h1 class="text-4xl font-extrabold leading-tight sm:text-5xl">{{ __('cabinet.landing.hero_title') }}</h1>
            <p class="mt-4 max-w-lg text-lg text-white/70">{{ __('cabinet.landing.hero_sub') }}</p>
        </div>

        <div class="mt-10 grid gap-4 sm:grid-cols-3">
            @php
                $cabinets = [
                    [__('cabinet.landing.institution'), __('cabinet.landing.institution_desc'), '/merchant', 'app.edu-gate.uz'],
                    [__('cabinet.landing.partner'), __('cabinet.landing.partner_desc'), '/partner', 'partner.edu-gate.uz'],
                    [__('cabinet.landing.admin'), __('cabinet.landing.admin_desc'), '/admin', 'admin.edu-gate.uz'],
                ];
            @endphp
            @foreach ($cabinets as [$name, $desc, $url, $host])
                <a href="{{ $url }}"
                   class="group rounded-card bg-white/95 p-5 shadow-eg-lg transition hover:-translate-y-1 hover:bg-white">
                    <p class="text-lg font-bold text-eg-navy">{{ $name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $desc }}</p>
                    <p class="mt-4 text-sm font-semibold text-eg-blue group-hover:underline">{{ __('cabinet.landing.sign_in_arrow') }}</p>
                    <p class="mt-3 eg-mono text-xs text-slate-400">{{ $host }}</p>
                </a>
            @endforeach
        </div>

        <p class="mt-8 text-sm text-white/50">
            {{ __('cabinet.landing.api_note') }} <span class="eg-mono">/api/v1</span>
        </p>
    </main>

    <footer class="py-8 text-sm text-white/40">© {{ date('Y') }} EduGate · edu-gate.uz</footer>
</div>
</body>
</html>
