<div class="space-y-6">
    <x-eg.demo-banner />
    <p class="-mt-2 text-sm text-eg-muted">{{ __('ext.reports.subtitle') }}</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Payment registry', 'All payments in a period', 'document'],
            ['Outstanding balances', 'Students with unpaid fees', 'wallet'],
            ['Settlement report', 'Payouts to your bank account', 'receipt'],
            ['Collection summary', 'Monthly collection totals', 'chart'],
            ['Student roster', 'Students by department', 'users'],
            ['Refunds', 'Refunded transactions', 'card'],
        ] as [$title, $desc, $icon])
            <div class="flex flex-col rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <span class="mb-3 grid h-10 w-10 place-items-center rounded-lg bg-eg-blue/10 text-eg-blue">
                    <x-eg.icon :name="$icon" class="h-5 w-5" />
                </span>
                <p class="font-semibold text-eg-ink">{{ $title }}</p>
                <p class="mt-1 flex-1 text-sm text-eg-muted">{{ $desc }}</p>
                <div class="mt-4 flex gap-2">
                    <button class="eg-btn eg-btn--outline !h-8 !px-3 text-xs" onclick="return false">CSV</button>
                    <button class="eg-btn eg-btn--outline !h-8 !px-3 text-xs" onclick="return false">XLSX</button>
                    <button class="eg-btn eg-btn--outline !h-8 !px-3 text-xs" onclick="return false">PDF</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
