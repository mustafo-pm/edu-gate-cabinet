@php
    $valid = $receipt->isValid();
    $status = $receipt->status();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A receipt carries someone's name and what they paid. It should never
         turn up in a search engine. --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ __('receipt.title') }} {{ $receipt->number }} · EduGate</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-eg-surface px-4 py-8 font-sans text-eg-ink antialiased">

<div class="mx-auto max-w-lg">

    <div class="mb-6 flex justify-center">
        <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="h-8 w-auto dark:hidden">
        <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="hidden h-8 w-auto dark:block">
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">

        {{-- Status band. Read live from the payment, never from the snapshot:
             a printed receipt can say "paid" long after a refund. --}}
        @if ($valid)
            <div class="bg-eg-success px-6 py-5 text-center text-white">
                <div class="text-3xl">✅</div>
                <div class="mt-1 text-lg font-bold tracking-tight">{{ __('receipt.confirmed') }}</div>
            </div>
        @else
            <div class="bg-eg-danger px-6 py-5 text-center text-white">
                <div class="text-3xl">❌</div>
                <div class="mt-1 text-lg font-bold tracking-tight">{{ __('receipt.not_valid') }}</div>
                <div class="mt-1 text-sm text-white/80">{{ __('receipt.status_'.$status->value) }}</div>
            </div>
        @endif

        <dl class="divide-y divide-eg-border/60 px-6 py-2 text-sm">
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-eg-muted">{{ __('receipt.number') }}</dt>
                <dd class="eg-mono font-semibold">{{ $receipt->number }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-eg-muted">{{ __('receipt.institution') }}</dt>
                <dd class="text-right font-medium">{{ $receipt->institution_name }}</dd>
            </div>
            @if ($receipt->student_name)
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-eg-muted">{{ __('receipt.student') }}</dt>
                    <dd class="text-right font-medium">
                        {{ $receipt->student_name }}
                        @if ($receipt->student_number)
                            <span class="block text-xs text-eg-muted">{{ $receipt->student_number }}</span>
                        @endif
                    </dd>
                </div>
            @endif
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-eg-muted">{{ __('receipt.amount') }}</dt>
                <dd class="text-lg font-bold">{{ \App\Support\Money::format($receipt->amount) }}</dd>
            </div>
            @if ($receipt->psp_name)
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-eg-muted">{{ __('receipt.via') }}</dt>
                    <dd class="font-medium">{{ $receipt->psp_name }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-eg-muted">{{ __('receipt.paid_at') }}</dt>
                <dd class="font-medium">{{ $receipt->paid_at?->format('d.m.Y  H:i') ?? '—' }}</dd>
            </div>
        </dl>

        <div class="border-t border-eg-border/60 px-6 py-6 text-center">
            <div class="inline-block rounded-lg bg-white p-3">{!! $qr !!}</div>
            <p class="mx-auto mt-3 max-w-xs text-xs leading-relaxed text-eg-muted">
                {{ __('receipt.qr_hint') }}
            </p>
        </div>

        {{-- Rendered at request time, so a screenshot is distinguishable from a
             live check — the timestamp on a forwarded image will be stale. --}}
        <div class="border-t border-eg-border/60 bg-eg-surface-2 px-6 py-3 text-center text-xs text-eg-muted">
            {{ __('receipt.checked_at') }}: {{ $checkedAt->format('d.m.Y  H:i') }} · edu-gate.uz
        </div>
    </div>

    <div class="mt-5 text-center">
        <a href="{{ route('receipt.pdf', $receipt->code) }}" class="eg-btn eg-btn--primary">
            {{ __('receipt.download_pdf') }}
        </a>
    </div>

    <p class="mt-6 text-center text-xs text-eg-muted">{{ __('receipt.footer') }}</p>
</div>

</body>
</html>
