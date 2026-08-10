@php
    $valid = $receipt->isValid();
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{-- DejaVu ships with the PDF renderer and covers Cyrillic and the Uzbek
         Latin letters (o', g'). The default font drops them silently. --}}
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; padding: 24px; color: #0D1929; font-size: 11px; }
        .brand { font-size: 20px; font-weight: bold; color: #0878FF; }
        .band { margin: 14px 0; padding: 12px; text-align: center; color: #fff;
                background: {{ $valid ? '#059669' : '#DC2626' }}; border-radius: 6px; }
        .band .t { font-size: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 7px 0; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
        td.k { color: #64748B; width: 45%; }
        td.v { text-align: right; font-weight: bold; }
        .amount { font-size: 15px; }
        .qr { margin-top: 18px; text-align: center; }
        .hint { margin-top: 6px; font-size: 9px; color: #64748B; }
        .foot { margin-top: 16px; padding-top: 10px; border-top: 1px solid #E2E8F0;
                font-size: 9px; color: #64748B; text-align: center; }
    </style>
</head>
<body>

<div class="brand">EduGate</div>

<div class="band">
    <div class="t">{{ $valid ? __('receipt.confirmed') : __('receipt.not_valid') }}</div>
</div>

<table>
    <tr><td class="k">{{ __('receipt.number') }}</td><td class="v">{{ $receipt->number }}</td></tr>
    <tr><td class="k">{{ __('receipt.institution') }}</td><td class="v">{{ $receipt->institution_name }}</td></tr>
    @if ($receipt->student_name)
        <tr><td class="k">{{ __('receipt.student') }}</td>
            <td class="v">{{ $receipt->student_name }}<br><span style="font-weight:normal;color:#64748B">{{ $receipt->student_number }}</span></td></tr>
    @endif
    <tr><td class="k">{{ __('receipt.amount') }}</td>
        <td class="v amount">{{ \App\Support\Money::format($receipt->amount) }}</td></tr>
    @if ($receipt->psp_name)
        <tr><td class="k">{{ __('receipt.via') }}</td><td class="v">{{ $receipt->psp_name }}</td></tr>
    @endif
    <tr><td class="k">{{ __('receipt.paid_at') }}</td>
        <td class="v">{{ $receipt->paid_at?->format('d.m.Y  H:i') ?? '—' }}</td></tr>
</table>

<div class="qr">
    <img src="{{ $qr }}" width="140" height="140" alt="QR">
    <div class="hint">{{ __('receipt.qr_hint') }}</div>
</div>

{{-- The printed sheet is a snapshot. Says so, because the status on paper can
     be out of date the moment a refund happens. --}}
<div class="foot">
    {{ __('receipt.printed_at') }}: {{ $checkedAt->format('d.m.Y  H:i') }}<br>
    {{ __('receipt.pdf_notice') }}
</div>

</body>
</html>
