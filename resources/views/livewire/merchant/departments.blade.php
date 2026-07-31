@php
    $departments = \App\Models\Department::withCount('students')->orderBy('name')->get();
    // Demo fallback rows so the page looks populated even before real data.
    $demoRows = [
        ['Faculty of Economics', 'ECON', 420],
        ['Faculty of Information Technologies', 'IT', 660],
        ['Faculty of Law', 'LAW', 310],
        ['Faculty of Foreign Languages', 'LANG', 250],
    ];
@endphp

<div class="space-y-6" x-data="{ add: false }">
    <x-eg.demo-banner />

    <div class="flex items-center justify-between">
        <p class="-mt-2 text-sm text-eg-muted">{{ __('ext.dept.subtitle') }}</p>
        <button type="button" @click="add = true" class="eg-btn eg-btn--primary">
            <x-eg.icon name="sitemap" class="h-4 w-4" /> {{ __('ext.dept.add') }}
        </button>
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('ext.dept.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('ext.dept.code') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('ext.dept.students') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($demoRows as [$name, $code, $count])
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3 font-medium text-eg-ink">{{ $name }}</td>
                        <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $code }}</span></td>
                        <td class="px-5 py-3 text-right font-semibold text-eg-ink">{{ number_format($count) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div x-show="add" x-cloak class="fixed inset-0 z-40 flex justify-end bg-eg-navy/40" @click.self="add=false">
        <div class="h-full w-full max-w-md bg-eg-card p-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-eg-ink">{{ __('ext.dept.add') }}</h2>
                <button @click="add=false" class="text-eg-muted hover:text-eg-ink">✕</button>
            </div>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ext.dept.name') }}</label>
                    <input type="text" class="eg-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('ext.dept.code') }}</label>
                    <input type="text" class="eg-input">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" class="eg-btn eg-btn--primary" onclick="return false">{{ __('cabinet.ui.save') }}</button>
                    <button type="button" @click="add=false" class="eg-btn eg-btn--outline">{{ __('cabinet.ui.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
