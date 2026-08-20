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
    {{-- Sidebar --}}
    <aside class="hidden w-64 shrink-0 flex-col border-r border-eg-border bg-eg-card lg:flex">
        <div class="flex h-16 items-center border-b border-eg-border px-5">
            <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="h-7 w-auto dark:hidden">
            <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="hidden h-7 w-auto dark:block">
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-5">
            @php
                use App\Support\CabinetRoles;

                // Fourth element is the permission the link needs. A link the
                // user cannot follow is worse than a missing one: they click it
                // and get a 403 with no explanation of why.
                $sections = [
                    __('ext.sec.statistics') => [
                        ['merchant.dashboard', __('cabinet.nav.dashboard'), 'grid'],
                        ['merchant.analytics', __('ext.menu.analytics'), 'chart'],
                    ],
                    __('ext.sec.student_affairs') => [
                        ['merchant.students', __('cabinet.nav.students'), 'users', CabinetRoles::STUDENTS_VIEW],
                        ['merchant.departments', __('cabinet.departments.title'), 'sitemap', CabinetRoles::STUDENTS_MANAGE],
                    ],
                    __('ext.sec.accounting') => [
                        ['merchant.schedules', __('cabinet.nav.schedules'), 'calendar', CabinetRoles::SCHEDULES],
                        ['merchant.transactions', __('cabinet.nav.payments'), 'card', CabinetRoles::PAYMENTS_VIEW],
                        ['merchant.reports', __('ext.menu.reports'), 'document', CabinetRoles::REPORTS],
                    ],
                    __('ext.sec.communications') => [
                        ['merchant.messaging', __('ext.menu.messaging'), 'send'],
                    ],
                    __('ext.sec.university') => [
                        ['merchant.profile', __('cabinet.profile.title'), 'building', CabinetRoles::PROFILE],
                        ['merchant.bank-accounts', __('cabinet.bank_accounts.title'), 'wallet', CabinetRoles::BANK_ACCOUNTS],
                        ['merchant.accounts', __('cabinet.staff.title'), 'userplus', CabinetRoles::STAFF],
                    ],
                ];
            @endphp
            @foreach ($sections as $section => $links)
                @php
                    $links = array_values(array_filter(
                        $links,
                        fn ($l) => ! isset($l[3]) || auth('merchant')->user()?->can($l[3]),
                    ));
                @endphp
                @continue(! $links)
                <div>
                    <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-eg-muted">{{ $section }}</p>
                    <div class="space-y-0.5">
                        @foreach ($links as $link)
                            @php([$route, $label, $icon] = [$link[0], $link[1], $link[2]])
                            <a href="{{ route($route) }}" wire:navigate
                               class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors
                                      {{ request()->routeIs($route)
                                          ? 'bg-eg-blue/10 text-eg-blue'
                                          : 'text-eg-text hover:bg-eg-surface2 hover:text-eg-ink' }}">
                                <x-eg.icon :name="$icon" class="h-[18px] w-[18px] shrink-0 {{ request()->routeIs($route) ? '' : 'text-eg-muted group-hover:text-eg-ink' }}" />
                                <span>{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
        <div class="border-t border-eg-border p-4">
            <p class="text-xs font-medium text-eg-ink">{{ auth('merchant')->user()?->merchant?->name }}</p>
            <p class="text-[11px] text-eg-muted">{{ \App\Support\Hosts::section('/merchant') }}</p>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-eg-border bg-eg-card/70 px-6 backdrop-blur">
            <h1 class="text-base font-semibold tracking-tight text-eg-ink">{{ $title ?? __('cabinet.nav.dashboard') }}</h1>
            <div class="flex items-center gap-2">
                <x-eg.controls />
                <livewire:notification-bell guard="merchant" />
                <div class="ml-1 hidden text-right sm:block">
                    <p class="text-sm font-medium leading-tight">{{ auth('merchant')->user()?->name }}</p>
                    <p class="text-xs text-eg-muted">{{ auth('merchant')->user()?->email }}</p>
                </div>
                <form method="POST" action="{{ route('merchant.logout') }}">
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
