<div>
    @if (session('status'))
        <div class="mb-6 rounded-card border border-eg-border bg-eg-card px-5 py-3 text-sm shadow-eg-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- One-time secret reveal, same pattern as API keys: shown once, never
         retrievable. Losing it means rotating, not recovering. --}}
    @if ($newSecret)
        <div class="mb-6 rounded-card border border-eg-blue/30 bg-eg-blue/5 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-bold text-eg-blue">{{ __('cabinet.webhooks.secret_title') }}</p>
                    <p class="mt-1 text-sm text-eg-text">{{ __('cabinet.webhooks.secret_hint') }}</p>
                </div>
                <button wire:click="dismissSecret" class="text-eg-muted hover:text-eg-ink">✕</button>
            </div>
            <p class="eg-mono mt-4 break-all rounded bg-eg-surface-2 px-3 py-2 text-sm">{{ $newSecret }}</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
            <h2 class="font-bold">{{ __('cabinet.webhooks.endpoint') }}</h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('cabinet.webhooks.url') }}</label>
                    <input type="url" wire:model="url" class="eg-input" placeholder="https://…">
                    @error('url') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs text-eg-muted">{{ __('cabinet.webhooks.url_hint') }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="enabled" class="rounded border-eg-border">
                    {{ __('cabinet.webhooks.enabled') }}
                </label>

                <button type="submit" class="eg-btn eg-btn--primary w-full">{{ __('cabinet.ui.save') }}</button>
            </form>

            @if ($hasSecret)
                <div class="mt-5 border-t border-eg-border/60 pt-4">
                    <button wire:click="rotateSecret"
                            wire:confirm="{{ __('cabinet.webhooks.rotate_confirm') }}"
                            class="text-xs font-semibold text-eg-blue hover:underline">
                        {{ __('cabinet.webhooks.rotate') }}
                    </button>
                    <button wire:click="sendTest" class="ml-4 text-xs text-eg-muted hover:text-eg-ink">
                        {{ __('cabinet.webhooks.send_test') }}
                    </button>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2">
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <h2 class="font-bold">{{ __('cabinet.webhooks.how') }}</h2>
                <p class="mt-2 text-sm text-eg-text">{{ __('cabinet.webhooks.how_body') }}</p>

                <pre class="eg-mono mt-3 overflow-x-auto rounded bg-eg-surface-2 p-3 text-xs leading-relaxed">signature = HMAC_SHA256(secret, timestamp + "." + raw_body)</pre>

                <p class="mt-3 text-xs text-eg-muted">{{ __('cabinet.webhooks.how_headers') }}</p>
            </div>

            <div class="mt-6 overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
                <div class="border-b border-eg-border/60 px-5 py-3">
                    <h2 class="font-bold">{{ __('cabinet.webhooks.recent') }}</h2>
                </div>

                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                        <tr class="border-b border-eg-border">
                            <th class="px-5 py-2 font-semibold">{{ __('cabinet.webhooks.event') }}</th>
                            <th class="px-5 py-2 font-semibold">{{ __('cabinet.webhooks.attempt') }}</th>
                            <th class="px-5 py-2 font-semibold">{{ __('cabinet.webhooks.result') }}</th>
                            <th class="px-5 py-2 font-semibold">{{ __('cabinet.payments.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deliveries as $d)
                            <tr class="border-b border-eg-border/60 last:border-0">
                                <td class="px-5 py-2"><span class="eg-mono text-xs">{{ $d->event }}</span></td>
                                <td class="px-5 py-2 text-eg-muted">#{{ $d->attempt }}</td>
                                <td class="px-5 py-2">
                                    @if ($d->succeeded)
                                        <x-eg.badge color="success">{{ $d->status_code }}</x-eg.badge>
                                    @else
                                        <x-eg.badge color="danger">{{ $d->status_code ?? __('cabinet.webhooks.failed') }}</x-eg.badge>
                                        @if ($d->error)
                                            <span class="ml-2 text-xs text-eg-muted">{{ Str::limit($d->error, 60) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-eg-muted">{{ $d->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-eg-muted">{{ __('cabinet.webhooks.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
