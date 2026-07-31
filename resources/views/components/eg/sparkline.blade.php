@props([
    'values' => [],   // array of numbers
    'height' => 36,
    'series' => 1,
])

@php
    $vals = array_map('floatval', $values);
    $n = max(1, count($vals));
    $max = max($vals ?: [1]);
    $min = min($vals ?: [0]);
    $span = max(0.0001, $max - $min);
    $coords = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? $i : 0;
        $y = 96 - (($v - $min) / $span) * 92;
        $coords[] = $x.' '.round($y, 2);
    }
    $vbW = max(1, $n - 1);
    $d = 'M'.implode(' L', $coords);
    $stroke = "var(--viz-{$series})";
@endphp

<svg viewBox="0 0 {{ $vbW }} 100" preserveAspectRatio="none" class="w-full" style="height: {{ $height }}px" aria-hidden="true">
    <path d="{{ $d }}" fill="none" stroke="{{ $stroke }}" stroke-width="2"
          stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" class="eg-line" pathLength="1" />
</svg>
