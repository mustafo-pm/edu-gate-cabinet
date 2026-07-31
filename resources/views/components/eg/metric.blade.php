@props([
    'label',
    'value',
    'icon' => 'chart',
    'delta' => null,       // signed number e.g. 8.4 (percent) or null
    'deltaGood' => true,   // is a positive delta good?
    'spark' => [],         // sparkline values
    'series' => 1,
])

@php
    $up = $delta !== null && $delta >= 0;
    // A positive delta is good/bad depending on the metric.
    $isGood = $up ? $deltaGood : ! $deltaGood;
    $deltaColor = $delta === null ? '' : ($isGood ? 'text-eg-success' : 'text-eg-danger');
@endphp

<div class="eg-rise rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
    <div class="flex items-start justify-between">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-eg-muted">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-bold tracking-tight text-eg-ink">{{ $value }}</p>
        </div>
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-eg-blue/10 text-eg-blue">
            <x-eg.icon :name="$icon" class="h-[18px] w-[18px]" />
        </span>
    </div>

    <div class="mt-3 flex items-center justify-between gap-3">
        @if ($delta !== null)
            <span class="inline-flex items-center gap-1 text-xs font-semibold {{ $deltaColor }}">
                <svg class="h-3.5 w-3.5 {{ $up ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                {{ number_format(abs($delta), 1) }}%
            </span>
        @else
            <span></span>
        @endif
        @if (count($spark) > 1)
            <div class="w-24"><x-eg.sparkline :values="$spark" :series="$series" :height="28" /></div>
        @endif
    </div>
</div>
