@props(['label', 'value', 'sub' => null, 'accent' => false])

<div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
    <p class="text-xs font-semibold uppercase tracking-wide text-eg-muted">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold {{ $accent ? 'text-eg-blue' : 'text-eg-ink' }}">{{ $value }}</p>
    @if ($sub)
        <p class="mt-1 text-sm text-eg-muted">{{ $sub }}</p>
    @endif
</div>
