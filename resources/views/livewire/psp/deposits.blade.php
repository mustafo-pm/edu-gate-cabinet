<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-eg-muted">{{ __('cabinet.deposits.intro') }}</p>
        <div class="rounded-card border border-eg-border bg-eg-card px-5 py-3 text-right shadow-eg-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-eg-muted">{{ __('cabinet.dash.current_balance') }}</p>
            <p class="text-xl font-bold text-eg-blue">{{ \App\Support\Money::format($balance) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.deposits.date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.deposits.type') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.deposits.reference') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.deposits.amount') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.deposits.balance_after') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deposits as $d)
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3 text-eg-muted">{{ $d->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3">
                            @if ($d->type === \App\Enums\LedgerType::Credit)
                                <x-eg.badge color="success">{{ __('cabinet.deposits.credit') }}</x-eg.badge>
                            @else
                                <x-eg.badge color="warning">{{ __('cabinet.deposits.debit') }}</x-eg.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $d->reference ?? $d->description ?? '—' }}</span></td>
                        <td class="px-5 py-3 text-right font-semibold {{ $d->type === \App\Enums\LedgerType::Credit ? 'text-eg-success' : 'text-eg-warning' }}">
                            {{ $d->type === \App\Enums\LedgerType::Credit ? '+' : '−' }}{{ \App\Support\Money::format($d->amount, false) }}
                        </td>
                        <td class="px-5 py-3 text-right">{{ \App\Support\Money::format($d->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.deposits.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $deposits->links() }}</div>
</div>
