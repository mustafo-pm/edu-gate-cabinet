@php
    use App\Support\StatusPalette;

    $valid = $receipt->isValid();
    $status = $receipt->status();

    // Colour follows the actual status, not a simple green/red split: a pending
    // payment is not a failure and must not be shown as one.
    $swatch = StatusPalette::for($status->color());

    $locale = app()->getLocale();
    $offered = (array) config('receipt.locales');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
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
<body class="min-h-screen bg-eg-surface px-4 py-6 font-sans text-eg-ink antialiased">

<div class="mx-auto max-w-md">

    {{-- Language is a link, not a control that remembers you: the page is
         forwarded and screenshotted, so the choice has to live in the URL. --}}
    <div class="mb-4 flex items-center justify-center gap-1 text-xs">
        @foreach ($offered as $code)
            <a href="{{ $receipt->url() }}?lang={{ $code }}"
               class="rounded-pill px-2.5 py-1 uppercase tracking-wide
                      {{ $code === $locale ? 'bg-eg-ink font-semibold text-white' : 'text-eg-muted hover:text-eg-ink' }}">
                {{ $code }}
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">

        {{-- Document header: who issued it, and its number. Mirrors how an
             official receipt is read — issuer first, reference second. --}}
        <div class="flex items-start justify-between gap-4 border-b border-eg-border/60 px-5 py-4">
            <img src="{{ asset('brand/edugate-blue.svg') }}" alt="EduGate" class="mt-0.5 h-6 w-auto dark:hidden">
            <img src="{{ asset('brand/edugate-white.svg') }}" alt="EduGate" class="mt-0.5 hidden h-6 w-auto dark:block">

            <div class="text-right leading-tight">
                <div class="eg-mono text-sm font-bold">{{ $receipt->number }}</div>
                <div class="mt-0.5 text-xs text-eg-muted">{{ $receipt->paid_at?->format('d.m.Y') ?? '—' }}</div>
            </div>
        </div>

        {{-- Status band. Read live from the payment, never from the snapshot:
             a printed receipt can say "paid" long after a refund. --}}
        <div class="px-5 py-5 text-center" style="background: {{ $swatch['background'] }}">
            <div style="color: {{ $swatch['text'] }}">
                <x-eg.icon :name="$status->icon()" class="mx-auto h-9 w-9" stroke-width="1.5" />
                <div class="mt-2 text-base font-bold tracking-tight">
                    {{ $valid ? __('receipt.confirmed') : __('receipt.not_valid') }}
                </div>
            </div>

            @unless ($valid)
                <div class="mt-1 text-xs text-eg-text">{{ __('receipt.status_'.$status->value) }}</div>
            @endunless
        </div>

        <dl class="divide-y divide-eg-border/60 px-5 text-sm">
            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-eg-muted">{{ __('receipt.institution') }}</dt>
                <dd class="text-right font-medium">{{ $receipt->institution_name }}</dd>
            </div>

            @if ($receipt->student_name)
                <div class="flex items-baseline justify-between gap-4 py-3">
                    <dt class="shrink-0 text-eg-muted">{{ __('receipt.student') }}</dt>
                    <dd class="text-right font-medium">
                        {{ $receipt->student_name }}
                        @if ($receipt->student_number)
                            <span class="eg-mono block text-xs font-normal text-eg-muted">{{ $receipt->student_number }}</span>
                        @endif
                    </dd>
                </div>
            @endif

            {{-- The amount is what the payer came to check, so it gets the
                 weight rather than sitting in the same rhythm as the rest. --}}
            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-eg-muted">{{ __('receipt.amount') }}</dt>
                <dd class="text-right text-lg font-bold">{{ \App\Support\Money::format($receipt->amount) }}</dd>
            </div>

            @if ($receipt->psp_name)
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="shrink-0 text-eg-muted">{{ __('receipt.via') }}</dt>
                    <dd class="flex items-center gap-2 text-right font-medium">
                        @if ($logo = $receipt->pspLogoUrl())
                            <img src="{{ $logo }}" alt="" class="h-5 w-auto max-w-20 object-contain">
                        @endif
                        {{ $receipt->psp_name }}
                    </dd>
                </div>
            @endif

            <div class="flex items-baseline justify-between gap-4 py-3">
                <dt class="shrink-0 text-eg-muted">{{ __('receipt.paid_at') }}</dt>
                <dd class="text-right font-medium">{{ $receipt->paid_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            </div>
        </dl>

        <div class="border-t border-eg-border/60 px-5 py-5 text-center">
            {{-- Fixed box: the writer emits its own dimensions, and without a
                 frame the code fills the width of a phone. --}}
            <div class="mx-auto h-36 w-36 rounded-lg bg-white p-2 [&>svg]:h-full [&>svg]:w-full">
                {!! $qr !!}
            </div>
            <p class="mx-auto mt-3 max-w-xs text-xs leading-relaxed text-eg-muted">
                {{ __('receipt.qr_hint') }}
            </p>
        </div>

        {{-- Rendered at request time, so a screenshot is distinguishable from a
             live check — the timestamp on a forwarded image will be stale. --}}
        <div class="border-t border-eg-border/60 bg-eg-surface-2 px-5 py-3 text-center text-xs text-eg-muted">
            {{ __('receipt.checked_at') }}: {{ $checkedAt->format('d.m.Y H:i') }} · edu-gate.uz
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ $receipt->pdfUrl() }}?lang={{ $locale }}" class="eg-btn eg-btn--ghost">
            {{ __('receipt.download_pdf') }}
        </a>
    </div>

    <p class="mt-5 text-center text-xs text-eg-muted">{{ __('receipt.footer') }}</p>
</div>

</body>
</html>
