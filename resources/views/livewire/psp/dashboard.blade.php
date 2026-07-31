<div class="space-y-6">
    {{-- Deposit balance highlight --}}
    <div class="overflow-hidden rounded-card text-white shadow-eg-lg" style="background:var(--eg-grad-cta)">
        <div class="flex flex-wrap items-end justify-between gap-4 p-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-white/70">{{ __('cabinet.dash.prepaid_balance') }}</p>
                <p class="mt-2 text-4xl font-extrabold">{{ \App\Support\Money::format($balance) }}</p>
                <p class="mt-1 text-sm text-white/60">{{ __('cabinet.dash.deducted_note') }}</p>
            </div>
            <a href="{{ route('psp.deposits') }}" wire:navigate class="eg-btn eg-btn--ghost">{{ __('cabinet.nav.deposits') }}</a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-eg.stat :label="__('cabinet.dash.completed_payments')" :value="number_format($txnCount)" />
        <x-eg.stat :label="__('cabinet.dash.total_volume')" :value="\App\Support\Money::format($volume)" accent />
        <x-eg.stat :label="__('cabinet.dash.commission')" :value="\App\Support\Money::format($commission)" />
    </div>

    {{-- Charts --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm lg:col-span-2">
            <h2 class="mb-4 font-bold">{{ __('cabinet.dash.volume_trend') }}</h2>
            <x-eg.bar-chart :bars="$trend" :height="200" />
        </div>
        <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
            <h2 class="mb-4 font-bold">{{ __('cabinet.dash.transactions_by_status') }}</h2>
            @if (count($statusSegments))
                <x-eg.donut :segments="$statusSegments" :caption="__('cabinet.nav.transactions')" />
            @else
                <p class="py-8 text-center text-sm text-eg-muted">{{ __('cabinet.ui.no_results') }}</p>
            @endif
        </div>
    </div>

    {{-- Recent transactions --}}
    <div class="rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <div class="flex items-center justify-between border-b border-eg-border px-5 py-4">
            <h2 class="font-bold">{{ __('cabinet.dash.recent_transactions') }}</h2>
            <a href="{{ route('psp.transactions') }}" wire:navigate class="text-sm font-semibold text-eg-blue">{{ __('cabinet.ui.view_all') }}</a>
        </div>
        @if ($recent->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.dash.no_transactions') }}</div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                    <tr class="border-b border-eg-border">
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.reference') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.institution') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.payments.amount') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $txn)
                        <tr class="border-b border-eg-border/60 last:border-0">
                            <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $txn->partner_transaction_id }}</span></td>
                            <td class="px-5 py-3 font-medium">{{ $txn->merchant?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-semibold">{{ \App\Support\Money::format($txn->amount) }}</td>
                            <td class="px-5 py-3"><x-eg.badge :color="$txn->status->color()">{{ $txn->status->label() }}</x-eg.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
