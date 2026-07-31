<div class="space-y-6">
    <x-eg.demo-banner />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-eg-muted">{{ __('ext.analytics.subtitle') }}</p>
        <div class="inline-flex rounded-lg border border-eg-border bg-eg-card p-0.5 shadow-eg-sm">
            @foreach (['month' => __('cabinet.ui.monthly'), 'quarter' => __('cabinet.ui.quarterly'), 'year' => __('cabinet.ui.yearly')] as $p => $lbl)
                <button wire:click="setPeriod('{{ $p }}')"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $period === $p ? 'bg-eg-blue text-white shadow-sm' : 'text-eg-muted hover:text-eg-ink' }}">
                    {{ $lbl }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Metrics --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-eg.metric label="Collection rate" value="86%" icon="chart" :delta="4.0" :spark="$rateSpark" :series="1" />
        <x-eg.metric label="Avg. days to pay" value="6.2" icon="calendar" :delta="-14.0" :deltaGood="false" :spark="$daysSpark" :series="2" />
        <x-eg.metric label="Active payers" value="1 240" icon="users" :delta="7.8" :spark="$payerSpark" :series="3" />
    </div>

    {{-- Trend (line) + channels (donut) --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="eg-rise rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm lg:col-span-2">
            <h2 class="mb-4 font-bold text-eg-ink">{{ __('cabinet.dash.collections_trend') }}</h2>
            <div wire:key="an-trend-{{ $period }}">
                <x-eg.area-chart :points="$trend" :height="240" type="money" />
            </div>
        </div>
        <div class="eg-rise rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
            <h2 class="mb-4 font-bold text-eg-ink">Payment channels</h2>
            <x-eg.donut :segments="$channels" caption="%" />
        </div>
    </div>

    {{-- Bar comparison --}}
    <div class="eg-rise rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
        <h2 class="mb-4 font-bold text-eg-ink">Collections by {{ __('cabinet.ui.'.$period.'ly') }}</h2>
        <div wire:key="an-bars-{{ $period }}">
            <x-eg.bar-chart :bars="$bars" :height="240" type="money" />
        </div>
    </div>
</div>
