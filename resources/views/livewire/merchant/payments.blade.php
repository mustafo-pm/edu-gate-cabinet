<div>
    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.reference') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.student') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.via') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.payments.amount') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.payments.net') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.date') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $txn)
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $txn->partner_transaction_id }}</span></td>
                        <td class="px-5 py-3 font-medium">{{ $txn->student?->fullName() ?? '—' }}</td>
                        <td class="px-5 py-3 text-eg-text">{{ $txn->psp?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">{{ \App\Support\Money::format($txn->amount) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ \App\Support\Money::format($txn->net_amount) }}</td>
                        <td class="px-5 py-3"><x-eg.badge :color="$txn->status->color()">{{ $txn->status->label() }}</x-eg.badge></td>
                        <td class="px-5 py-3 text-eg-muted">{{ $txn->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            {{-- Opens the public page directly when a receipt already
                                 exists; otherwise issues one first, then shows the link. --}}
                            @if ($txn->receipt)
                                <a href="{{ $txn->receipt->url() }}" target="_blank" rel="noopener"
                                   class="text-xs font-semibold text-eg-blue hover:underline">
                                    {{ __('receipt.open') }}
                                </a>
                                <button type="button" wire:click="receipt({{ $txn->id }})"
                                        class="ml-3 text-xs text-eg-muted hover:text-eg-ink">
                                    {{ __('receipt.copy_link') }}
                                </button>
                            @else
                                <button type="button" wire:click="receipt({{ $txn->id }})"
                                        wire:loading.attr="disabled"
                                        class="text-xs font-semibold text-eg-blue hover:underline">
                                    {{ __('receipt.open') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.payments.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>

    {{-- Link dialog. The URL is selectable and copyable rather than only a
         button, because staff read it out over the phone as often as they
         paste it. --}}
    @if ($receiptUrl)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="closeReceipt">
            <div class="w-full max-w-md rounded-card border border-eg-border bg-eg-card p-6 shadow-eg-lg">
                <h2 class="text-base font-bold">{{ __('receipt.title') }} {{ $receiptNumber }}</h2>

                <div class="mt-4" x-data="{ copied: false }">
                    <input type="text" readonly value="{{ $receiptUrl }}"
                           class="eg-input eg-mono text-xs"
                           x-ref="link"
                           onclick="this.select()">

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button" class="eg-btn eg-btn--primary flex-1"
                                x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied">{{ __('receipt.copy_link') }}</span>
                            <span x-show="copied" x-cloak>{{ __('receipt.copied') }}</span>
                        </button>

                        <a href="{{ $receiptUrl }}" target="_blank" rel="noopener"
                           class="eg-btn eg-btn--ghost">{{ __('receipt.open') }}</a>
                    </div>
                </div>

                <button type="button" wire:click="closeReceipt"
                        class="mt-4 w-full text-center text-xs text-eg-muted hover:text-eg-ink">
                    {{ __('cabinet.ui.close') }}
                </button>
            </div>
        </div>
    @endif
</div>
