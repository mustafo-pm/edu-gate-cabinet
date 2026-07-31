@props([
    'points' => [],       // [ ['label'=>'Jan','value'=>1234], ... ]  value in tiyin (money) or raw
    'height' => 240,
    'type' => 'money',    // money | count | percent
    'series' => 1,          // series colour slot 1..3
])

@php
    use App\Support\Money;
    $n = max(1, count($points));
    $vals = array_map(fn ($p) => (float) $p['value'], $points);
    $max = max(1, ...($vals ?: [1]));
    // Nice headroom.
    $topPad = 10; $plot = 100 - $topPad;

    $fmt = function ($v) use ($type) {
        return match ($type) {
            'count' => number_format((int) $v),
            'percent' => rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%',
            default => Money::format((int) $v),
        };
    };

    // Build coordinates in a (n-1) x 100 viewBox (y down).
    $coords = [];
    foreach ($points as $i => $p) {
        $x = $n > 1 ? $i : 0;
        $y = $topPad + $plot * (1 - ((float) $p['value']) / $max);
        $coords[] = ['x' => $x, 'y' => round($y, 2)];
    }
    $vbW = max(1, $n - 1);
    $line = collect($coords)->map(fn ($c, $i) => ($i === 0 ? 'M' : 'L').$c['x'].' '.$c['y'])->implode(' ');
    $area = 'M0 100 '.collect($coords)->map(fn ($c) => 'L'.$c['x'].' '.$c['y'])->implode(' ').' L'.$vbW.' 100 Z';

    // JS point data for hover (positions as %).
    $js = collect($points)->map(fn ($p, $i) => [
        'l' => $p['label'],
        'v' => $fmt($p['value']),
        'x' => $n > 1 ? round($i / ($n - 1) * 100, 3) : 50,
        'y' => round($coords[$i]['y'], 2),
    ])->values();

    $stroke = "var(--viz-{$series})";
    $gid = 'egfill'.$series.substr(md5($line.$height), 0, 6);
@endphp

<div class="relative select-none" style="height: {{ $height }}px"
     x-data="{ i: null, pts: {{ Illuminate\Support\Js::from($js) }} }"
     @mousemove="i = Math.max(0, Math.min(pts.length-1, Math.round(($event.offsetX / $el.clientWidth) * (pts.length-1))))"
     @mouseleave="i = null">

    {{-- Plot --}}
    <svg viewBox="0 0 {{ $vbW }} 100" preserveAspectRatio="none" class="absolute inset-0 h-full w-full">
        <defs>
            <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="{{ $stroke }}" stop-opacity="0.26" />
                <stop offset="100%" stop-color="{{ $stroke }}" stop-opacity="0.02" />
            </linearGradient>
        </defs>
        {{-- gridlines --}}
        @foreach ([10, 32.5, 55, 77.5, 100] as $gy)
            <line x1="0" y1="{{ $gy }}" x2="{{ $vbW }}" y2="{{ $gy }}"
                  stroke="var(--viz-grid)" stroke-width="0.5" vector-effect="non-scaling-stroke" />
        @endforeach
        <path d="{{ $area }}" fill="url(#{{ $gid }})" class="eg-area" />
        <path d="{{ $line }}" fill="none" stroke="{{ $stroke }}" stroke-width="2"
              stroke-linejoin="round" stroke-linecap="round" pathLength="1"
              vector-effect="non-scaling-stroke" class="eg-line" />
    </svg>

    {{-- Dots (HTML overlay so they don't distort) --}}
    @foreach ($js as $k => $p)
        <span class="eg-dot absolute h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 bg-eg-card"
              style="left: {{ $p['x'] }}%; top: {{ $p['y'] }}%; border-color: {{ $stroke }}; animation-delay: {{ 0.4 + $k * 0.05 }}s"></span>
    @endforeach

    {{-- Hover crosshair + highlighted dot + tooltip --}}
    <template x-if="i !== null">
        <div>
            <div class="pointer-events-none absolute top-0 bottom-5 w-px bg-eg-border"
                 :style="`left: ${pts[i].x}%`"></div>
            <div class="pointer-events-none absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-eg-card"
                 :style="`left: ${pts[i].x}%; top: ${pts[i].y}%; background: {{ $stroke }}`"></div>
            <div class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg border border-eg-border bg-eg-card px-2.5 py-1.5 text-xs shadow-eg-lg"
                 :style="`left: ${Math.min(88, Math.max(12, pts[i].x))}%; top: ${pts[i].y}%; margin-top: -10px`">
                <div class="font-semibold text-eg-ink" x-text="pts[i].v"></div>
                <div class="text-eg-muted" x-text="pts[i].l"></div>
            </div>
        </div>
    </template>

    {{-- max reference label --}}
    <div class="pointer-events-none absolute left-0 top-1 text-[10px] font-medium tabular-nums text-eg-muted">{{ $fmt($max) }}</div>

    {{-- x labels --}}
    <div class="absolute inset-x-0 bottom-0 flex justify-between px-0.5 text-[11px] font-medium text-eg-muted">
        @foreach ($points as $p)
            <span>{{ $p['label'] }}</span>
        @endforeach
    </div>
</div>
