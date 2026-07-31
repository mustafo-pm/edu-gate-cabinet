@props([
    'bars' => [],        // [ ['label'=>'Q1','value'=>1234], ... ]
    'height' => 240,
    'type' => 'money',   // money | count | percent
    'series' => 1,
])

@php
    use App\Support\Money;
    $vals = array_map(fn ($b) => (float) $b['value'], $bars);
    $max = max(1, ...($vals ?: [1]));
    $plotH = $height - 26;   // leave room for x labels

    $fmt = fn ($v) => match ($type) {
        'count' => number_format((int) $v),
        'percent' => rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%',
        default => Money::format((int) $v),
    };
    $fill = "var(--viz-{$series})";
@endphp

<div class="w-full">
    <div class="relative" style="height: {{ $plotH }}px">
        {{-- gridlines --}}
        @foreach ([0, 25, 50, 75] as $g)
            <div class="absolute inset-x-0 border-t border-eg-border/70" style="top: {{ $g }}%"></div>
        @endforeach
        <div class="absolute inset-x-0 bottom-0 border-t" style="border-color: var(--viz-axis)"></div>

        {{-- bars --}}
        <div class="absolute inset-0 flex items-end gap-2">
            @foreach ($bars as $i => $b)
                @php $h = max(1.5, ((float) $b['value']) / $max * 100); @endphp
                <div class="group relative flex h-full flex-1 items-end">
                    {{-- track --}}
                    <div class="absolute inset-x-0 bottom-0 top-0 rounded-t-md" style="background: var(--viz-track); opacity:.5"></div>
                    {{-- value bar --}}
                    <div class="eg-bar relative w-full rounded-t-md transition-opacity group-hover:opacity-90"
                         style="height: {{ $h }}%; background: {{ $fill }}; animation-delay: {{ $i * 0.06 }}s"></div>
                    {{-- tooltip --}}
                    <div class="pointer-events-none absolute left-1/2 bottom-full z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg border border-eg-border bg-eg-card px-2.5 py-1.5 text-xs opacity-0 shadow-eg-lg transition-opacity group-hover:opacity-100">
                        <div class="font-semibold text-eg-ink">{{ $fmt($b['value']) }}</div>
                        <div class="text-eg-muted">{{ $b['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- x labels --}}
    <div class="mt-2 flex gap-2">
        @foreach ($bars as $b)
            <span class="flex-1 text-center text-[11px] font-medium text-eg-muted">{{ $b['label'] }}</span>
        @endforeach
    </div>
</div>
