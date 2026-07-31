@props(['color' => 'muted'])

@php
    // Map semantic name → CSS colour var driving the .eg-badge tint.
    $map = [
        'success'    => 'var(--eg-success)',
        'processing' => 'var(--eg-blue)',
        'warning'    => 'var(--eg-warning)',
        'danger'     => 'var(--eg-danger)',
        'refund'     => 'var(--eg-refund)',
        'muted'      => 'var(--eg-muted)',
    ];
    $c = $map[$color] ?? $map['muted'];
@endphp

<span {{ $attributes->merge(['class' => 'eg-badge']) }} style="--c: {{ $c }}">
    {{ $slot }}
</span>
