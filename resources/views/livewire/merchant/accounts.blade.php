@php
    $me = auth('merchant')->user();
    $staff = [
        [$me?->name ?? 'You', $me?->email ?? '—', 'role_admin', true],
        ['Bekzod Rahimov', 'bekzod@tdu.uz', 'role_accountant', true],
        ['Nodira Ismoilova', 'nodira@tdu.uz', 'role_accountant', true],
        ['Sardor Aliyev', 'sardor@tdu.uz', 'role_viewer', false],
    ];
@endphp

<div class="space-y-6" x-data="{ add: false }">
    <x-eg.demo-banner />

    <div class="flex items-center justify-between">
        <p class="-mt-2 text-sm text-eg-muted">{{ __('ext.acc.subtitle') }}</p>
        <button type="button" @click="add = true" class="eg-btn eg-btn--primary">
            <x-eg.icon name="userplus" class="h-4 w-4" /> {{ __('ext.acc.add') }}
        </button>
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('ext.acc.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('ext.acc.email') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('ext.acc.role') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('ext.acc.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($staff as [$name, $email, $role, $active])
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-eg-blue/10 text-xs font-bold text-eg-blue">
                                    {{ mb_substr($name, 0, 1) }}
                                </span>
                                <span class="font-medium text-eg-ink">{{ $name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-eg-text">{{ $email }}</td>
                        <td class="px-5 py-3"><x-eg.badge color="muted">{{ __('ext.acc.'.$role) }}</x-eg.badge></td>
                        <td class="px-5 py-3">
                            <x-eg.badge :color="$active ? 'success' : 'warning'">
                                {{ $active ? __('cabinet.status.active') : __('cabinet.status.inactive') }}
                            </x-eg.badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Add drawer --}}
    <div x-show="add" x-cloak class="fixed inset-0 z-40 flex justify-end bg-eg-navy/40" @click.self="add=false">
        <div class="h-full w-full max-w-md overflow-y-auto bg-eg-card p-6 shadow-2xl" x-transition.origin.right>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-eg-ink">{{ __('ext.acc.add') }}</h2>
                <button @click="add=false" class="text-eg-muted hover:text-eg-ink">✕</button>
            </div>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ext.acc.name') }}</label>
                    <input type="text" class="eg-input" placeholder="Ism Familiya">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ext.acc.email') }}</label>
                    <input type="email" class="eg-input" placeholder="xodim@tdu.uz">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ext.acc.role') }}</label>
                    <select class="eg-input">
                        <option>{{ __('ext.acc.role_admin') }}</option>
                        <option>{{ __('ext.acc.role_accountant') }}</option>
                        <option>{{ __('ext.acc.role_viewer') }}</option>
                    </select>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" class="eg-btn eg-btn--primary" onclick="return false">{{ __('ext.acc.invite') }}</button>
                    <button type="button" @click="add=false" class="eg-btn eg-btn--outline">{{ __('cabinet.ui.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
