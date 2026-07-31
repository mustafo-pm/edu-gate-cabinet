<div>
    {{-- One-time secret reveal --}}
    @if ($newSecret)
        <div class="mb-6 rounded-card border border-eg-blue/30 bg-eg-blue/5 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-bold text-eg-blue">{{ __('cabinet.keys.created_title') }}</p>
                    <p class="mt-1 text-sm text-eg-text">{{ __('cabinet.keys.created_hint') }}</p>
                </div>
                <button wire:click="dismissSecret" class="text-eg-muted hover:text-eg-ink">✕</button>
            </div>
            <div class="mt-4 space-y-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-eg-muted">{{ __('cabinet.keys.key_id') }}</p>
                    <p class="eg-mono break-all rounded bg-eg-surface2 px-3 py-2 text-sm">{{ $newKeyId }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-eg-muted">{{ __('cabinet.keys.secret') }}</p>
                    <p class="eg-mono break-all rounded bg-eg-surface2 px-3 py-2 text-sm">{{ $newSecret }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Generate form --}}
        <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
            <h2 class="font-bold">{{ __('cabinet.keys.generate') }}</h2>
            <form wire:submit="generate" class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.keys.name') }}</label>
                    <input type="text" wire:model="name" class="eg-input" placeholder="Production server">
                    @error('name') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.keys.environment') }}</label>
                    <select wire:model="environment" class="eg-input">
                        <option value="sandbox">{{ __('cabinet.keys.sandbox') }}</option>
                        <option value="live">{{ __('cabinet.keys.live') }}</option>
                    </select>
                </div>
                <button type="submit" class="eg-btn eg-btn--primary w-full">{{ __('cabinet.keys.generate') }}</button>
            </form>
        </div>

        {{-- Existing keys --}}
        <div class="lg:col-span-2 overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
            <div class="border-b border-eg-border px-5 py-4"><h2 class="font-bold">{{ __('cabinet.keys.your_keys') }}</h2></div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                    <tr class="border-b border-eg-border">
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.keys.name') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.keys.key_id') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.keys.environment') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('cabinet.keys.status') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($keys as $key)
                        <tr class="border-b border-eg-border/60 last:border-0">
                            <td class="px-5 py-3 font-medium">{{ $key->name }}</td>
                            <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $key->key_id }}</span></td>
                            <td class="px-5 py-3">
                                <x-eg.badge :color="$key->environment === \App\Enums\ApiEnvironment::Live ? 'processing' : 'muted'">
                                    {{ $key->environment === \App\Enums\ApiEnvironment::Live ? __('cabinet.keys.live') : __('cabinet.keys.sandbox') }}
                                </x-eg.badge>
                            </td>
                            <td class="px-5 py-3">
                                @if ($key->isActive())
                                    <x-eg.badge color="success">{{ __('cabinet.keys.active') }}</x-eg.badge>
                                @else
                                    <x-eg.badge color="danger">{{ __('cabinet.keys.revoked') }}</x-eg.badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($key->isActive())
                                    <button wire:click="revoke({{ $key->id }})"
                                            wire:confirm="{{ __('cabinet.keys.revoke_confirm') }}"
                                            class="text-sm font-semibold text-eg-danger hover:underline">{{ __('cabinet.keys.revoke') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.keys.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
