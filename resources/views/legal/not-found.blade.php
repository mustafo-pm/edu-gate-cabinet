<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('legal.not_found_title') }} · EduGate</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-eg-surface px-4 font-sans text-eg-ink antialiased">

<div class="max-w-sm text-center">
    <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="mx-auto h-7 w-auto">
    <h1 class="mt-6 text-lg font-bold">{{ __('legal.not_found_title') }}</h1>
    <p class="mt-2 text-sm text-eg-muted">{{ __('legal.not_found_body') }}</p>
</div>

</body>
</html>
