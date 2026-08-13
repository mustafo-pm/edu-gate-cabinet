@php
    $locale = app()->getLocale();
    $offered = ['uz', 'ru', 'en'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $version->title($locale) }} · EduGate</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-eg-surface px-4 py-8 font-sans text-eg-ink antialiased">

<div class="mx-auto max-w-3xl">

    <div class="mb-6 flex items-center justify-between gap-4">
        <a href="{{ url('/') }}">
            <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="h-6 w-auto">
        </a>

        <div class="flex items-center gap-1 text-xs">
            @foreach ($offered as $code)
                <a href="?lang={{ $code }}"
                   class="rounded-pill px-2.5 py-1 uppercase tracking-wide
                          {{ $code === $locale ? 'bg-eg-ink font-semibold text-white' : 'text-eg-muted hover:text-eg-ink' }}">
                    {{ $code }}
                </a>
            @endforeach
        </div>
    </div>

    <article class="rounded-card border border-eg-border bg-eg-card p-6 shadow-eg-sm sm:p-10">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $version->title($locale) }}</h1>

        {{-- Which text this is, in force since when. A legal document without a
             version and a date is not much use in an argument. --}}
        <p class="mt-2 text-xs text-eg-muted">
            {{ __('legal.version') }} {{ $version->version }}
            @if ($version->effective_from)
                · {{ __('legal.in_force_since') }} {{ $version->effective_from->format('d.m.Y') }}
            @endif
        </p>

        @if ($upcoming)
            <div class="mt-5 rounded-lg border border-eg-warning/30 bg-eg-warning/5 px-4 py-3 text-sm">
                {{ __('legal.upcoming_notice', [
                    'version' => $upcoming->version,
                    'date' => $upcoming->effective_from?->format('d.m.Y'),
                ]) }}
            </div>
        @endif

        {{-- Markdown rendered with HTML stripped, so admin-authored text cannot
             put markup — or a script — on a public page. --}}
        <div class="eg-prose mt-8">
            {!! $version->html($locale) !!}
        </div>
    </article>

    <p class="mt-6 text-center text-xs text-eg-muted">{{ __('legal.footer') }}</p>
</div>

</body>
</html>
