@props([
    'segments' => [],   // [ ['label' => 'Paid', 'value' => 5, 'hex' => '#059669'], ... ]
    'total' => null,    // center number (defaults to sum of values)
    'caption' => null,  // center caption
])

@php
    $sum = array_sum(array_map(fn ($s) => (int) $s['value'], $segments));
    $centerValue = $total ?? $sum;
    $r = 15.915;        // circumference = 100 → dasharray in %
    $offset = 25;       // start at top (12 o'clock)
@endphp

<div class="flex items-center gap-5">
    <svg viewBox="0 0 42 42" class="h-32 w-32 shrink-0 -rotate-0">
        <circle cx="21" cy="21" r="{{ $r }}" fill="none" stroke="currentColor" class="text-eg-surface2" stroke-width="5" />
        @php $acc = 0; @endphp
        @foreach ($segments as $s)
            @php
                $val = (int) $s['value'];
                $pct = $sum > 0 ? ($val / $sum) * 100 : 0;
                $dash = round($pct, 2);
                $dashOffset = ($offset - $acc + 100) % 100;
                $acc += $pct;
            @endphp
            @if ($val > 0)
                <circle cx="21" cy="21" r="{{ $r }}" fill="none"
                        stroke="{{ $s['hex'] }}" stroke-width="5"
                        stroke-dasharray="{{ $dash }} {{ 100 - $dash }}"
                        stroke-dashoffset="{{ $dashOffset }}"
                        transform="rotate(-90 21 21)">
                    <title>{{ $s['label'] }}: {{ $val }}</title>
                </circle>
            @endif
        @endforeach
        <text x="21" y="20" text-anchor="middle" class="fill-eg-ink" style="font-size:7px;font-weight:700">{{ $centerValue }}</text>
        @if ($caption)
            <text x="21" y="26" text-anchor="middle" class="fill-eg-muted" style="font-size:3px">{{ $caption }}</text>
        @endif
    </svg>

    <div class="space-y-1.5">
        @foreach ($segments as $s)
            <div class="flex items-center gap-2 text-sm">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $s['hex'] }}"></span>
                <span class="text-eg-text">{{ $s['label'] }}</span>
                <span class="ml-auto font-semibold text-eg-ink">{{ $s['value'] }}</span>
            </div>
        @endforeach
    </div>
</div>
