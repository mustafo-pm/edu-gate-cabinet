<div>
    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.reference') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.institution') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.payments.amount') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.payments.commission') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.date') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $txn)
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $txn->partner_transaction_id }}</span></td>
                        <td class="px-5 py-3 font-medium">{{ $txn->merchant?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ \App\Support\Money::format($txn->amount) }}</td>
                        <td class="px-5 py-3 text-right text-eg-muted">{{ \App\Support\Money::format($txn->commission_amount) }}</td>
                        <td class="px-5 py-3"><x-eg.badge :color="$txn->status->color()">{{ $txn->status->label() }}</x-eg.badge></td>
                        <td class="px-5 py-3 text-eg-muted">{{ $txn->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.dash.no_transactions') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
