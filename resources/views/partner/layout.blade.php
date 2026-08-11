<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('cabinet.nav.dashboard') }} · EduGate</title>
    <x-eg.head />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-eg-surface font-sans text-eg-ink antialiased">
<div class="flex min-h-screen">
    <aside class="hidden w-64 shrink-0 flex-col border-r border-eg-border bg-eg-card lg:flex">
        <div class="flex h-16 items-center border-b border-eg-border px-5">
            <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="h-7 w-auto dark:hidden">
            <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="hidden h-7 w-auto dark:block">
        </div>
        <div class="px-5 pb-1.5 pt-5 text-[11px] font-medium uppercase tracking-wider text-eg-muted">
            {{ __('cabinet.nav.partner') }}
        </div>
        <nav class="flex-1 space-y-0.5 px-3">
            @php
                $links = [
                    ['psp.dashboard', __('cabinet.nav.dashboard'), 'grid'],
                    ['psp.deposits', __('cabinet.nav.deposits'), 'wallet'],
                    ['psp.transactions', __('cabinet.nav.transactions'), 'receipt'],
                    ['psp.api-keys', __('cabinet.nav.api_keys'), 'key'],
                    ['psp.webhooks', __('cabinet.nav.webhooks'), 'send'],
                ];
            @endphp
            @foreach ($links as [$route, $label, $icon])
                <a href="{{ route($route) }}" wire:navigate
                   class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs($route)
                              ? 'bg-eg-blue/10 text-eg-blue'
                              : 'text-eg-text hover:bg-eg-surface2 hover:text-eg-ink' }}">
                    <x-eg.icon :name="$icon" class="h-[18px] w-[18px] shrink-0 {{ request()->routeIs($route) ? '' : 'text-eg-muted group-hover:text-eg-ink' }}" />
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        <div class="border-t border-eg-border p-4">
            <p class="text-xs font-medium text-eg-ink">{{ auth('psp')->user()?->psp?->name }}</p>
            <p class="text-[11px] text-eg-muted">{{ \App\Support\Hosts::section('/partner') }}</p>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-eg-border bg-eg-card/70 px-6 backdrop-blur">
            <h1 class="text-base font-semibold tracking-tight text-eg-ink">{{ $title ?? __('cabinet.nav.dashboard') }}</h1>
            <div class="flex items-center gap-2">
                <x-eg.controls />
                <livewire:notification-bell guard="psp" />
                <div class="ml-1 hidden text-right sm:block">
                    <p class="text-sm font-medium leading-tight">{{ auth('psp')->user()?->name }}</p>
                    <p class="text-xs text-eg-muted">{{ auth('psp')->user()?->email }}</p>
                </div>
                <form method="POST" action="{{ route('psp.logout') }}">
                    @csrf
                    <button type="submit" class="eg-btn eg-btn--outline !h-8 !px-3 text-sm">{{ __('cabinet.auth.sign_out') }}</button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-6">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-eg-success/30 bg-eg-success/10 px-4 py-3 text-sm text-eg-success">
                    {{ session('status') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
