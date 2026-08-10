<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('receipt.not_found_title') }} · EduGate</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-eg-surface px-4 font-sans text-eg-ink antialiased">
    <div class="w-full max-w-sm text-center">
        <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="mx-auto h-8 w-auto dark:hidden">
        <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="mx-auto hidden h-8 w-auto dark:block">

        <div class="mt-8 rounded-card border border-eg-border bg-eg-card p-8 shadow-eg-sm">
            <div class="text-4xl">🔍</div>
            <h1 class="mt-3 text-lg font-bold">{{ __('receipt.not_found_title') }}</h1>
            <p class="mt-2 text-sm text-eg-muted">{{ __('receipt.not_found_body') }}</p>
        </div>
    </div>
</body>
</html>
