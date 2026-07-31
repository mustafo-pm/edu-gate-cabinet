@php use App\Support\Money; @endphp

<div class="space-y-6">
    {{-- Period filter --}}
    <div class="flex items-center justify-between">
        <p class="text-sm text-eg-muted">{{ __('cabinet.dash.collections_trend') }}</p>
        <div class="inline-flex rounded-lg border border-eg-border bg-eg-card p-0.5 shadow-eg-sm">
            @foreach (['month' => __('cabinet.ui.monthly'), 'quarter' => __('cabinet.ui.quarterly'), 'year' => __('cabinet.ui.yearly')] as $p => $lbl)
                <button wire:click="setPeriod('{{ $p }}')" wire:loading.attr="disabled"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === $p ? 'bg-eg-blue text-white shadow-sm' : 'text-eg-muted hover:text-eg-ink' }}">
                    {{ $lbl }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Metrics --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-eg.metric :label="__('cabinet.dash.collected')" :value="Money::format($collectedNow)"
                     icon="wallet" :delta="$deltaCollected" :spark="$collectedSeries" />
        <x-eg.metric :label="__('cabinet.nav.payments')" :value="number_format($txnNow)"
                     icon="card" :delta="$deltaTxn" :spark="$countsSeries" />
        <x-eg.metric :label="__('cabinet.dash.avg_payment')" :value="Money::format($avgNow)"
                     icon="chart" :delta="$deltaAvg" :spark="$collectedSeries" />
        <x-eg.metric :label="__('cabinet.dash.outstanding')" :value="Money::format($outstanding)"
                     icon="document" :deltaGood="false" />
    </div>

    {{-- Trend + status --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="eg-rise rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-bold text-eg-ink">{{ __('cabinet.dash.collections_trend') }}</h2>
                <span class="flex items-center gap-1.5 text-xs text-eg-muted">
                    <span class="h-2.5 w-2.5 rounded-full" style="background: var(--viz-1)"></span>
                    {{ __('cabinet.dash.collected') }}
                </span>
            </div>
            <div wire:key="trend-{{ $period }}">
                <x-eg.area-chart :points="$trend" :height="240" type="money" />
            </div>
        </div>
        <div class="eg-rise rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
            <h2 class="mb-4 font-bold text-eg-ink">{{ __('cabinet.dash.schedules_by_status') }}</h2>
            @if (count($statusSegments))
                <x-eg.donut :segments="$statusSegments" :caption="__('cabinet.nav.schedules')" />
            @else
                <p class="py-8 text-center text-sm text-eg-muted">{{ __('cabinet.ui.no_results') }}</p>
            @endif
        </div>
    </div>

    {{-- Recent + top outstanding --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-card border border-eg-border bg-eg-card shadow-eg-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-eg-border px-5 py-4">
                <h2 class="font-bold text-eg-ink">{{ __('cabinet.dash.recent_payments') }}</h2>
                <a href="{{ route('merchant.transactions') }}" wire:navigate class="text-sm font-semibold text-eg-blue">{{ __('cabinet.ui.view_all') }}</a>
            </div>
            @if ($recent->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.dash.no_payments') }}</div>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                        <tr class="border-b border-eg-border">
                            <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.student') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.reference') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.payments.amount') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $txn)
                            <tr class="border-b border-eg-border/60 last:border-0">
                                <td class="px-5 py-3 font-medium">{{ $txn->student?->fullName() ?? '—' }}</td>
                                <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $txn->partner_transaction_id }}</span></td>
                                <td class="px-5 py-3 text-right font-semibold">{{ Money::format($txn->amount) }}</td>
                                <td class="px-5 py-3"><x-eg.badge :color="$txn->status->color()">{{ $txn->status->label() }}</x-eg.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
            <div class="border-b border-eg-border px-5 py-4"><h2 class="font-bold text-eg-ink">{{ __('cabinet.dash.top_outstanding') }}</h2></div>
            <div class="divide-y divide-eg-border/60">
                @forelse ($topOutstanding as $row)
                    <div class="flex items-center justify-between px-5 py-3">
                        <span class="truncate pr-2 text-sm font-medium">{{ $row['name'] }}</span>
                        <span class="shrink-0 text-sm font-semibold text-eg-danger">{{ Money::format($row['amount']) }}</span>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-eg-muted">{{ __('cabinet.ui.no_results') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
