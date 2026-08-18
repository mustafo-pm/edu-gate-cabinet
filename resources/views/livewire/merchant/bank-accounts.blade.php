<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-card border border-eg-border bg-eg-card px-5 py-3 text-sm shadow-eg-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Why approval exists, said once, where it will be read. --}}
    <div class="rounded-card border border-eg-blue/25 bg-eg-blue/5 px-5 py-4 text-sm">
        {{ __('cabinet.bank_accounts.notice') }}
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <div class="flex items-center justify-between gap-4 border-b border-eg-border/60 px-5 py-4">
            <div>
                <h2 class="font-bold">{{ __('cabinet.bank_accounts.title') }}</h2>
                <p class="mt-0.5 text-sm text-eg-muted">
                    @if ($primary)
                        {{ __('cabinet.bank_accounts.settling_to', ['bank' => $primary->bank_name, 'account' => $primary->maskedNumber()]) }}
                    @else
                        {{ __('cabinet.bank_accounts.none_active') }}
                    @endif
                </p>
            </div>
            <button type="button" wire:click="$toggle('adding')" class="eg-btn eg-btn--ghost shrink-0">
                {{ __('cabinet.bank_accounts.add') }}
            </button>
        </div>

        @if ($adding)
            <form wire:submit="add" class="grid gap-4 border-b border-eg-border/60 bg-eg-surface-2 p-5 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.bank_accounts.bank') }}</label>
                    <input type="text" wire:model="bank_name" class="eg-input">
                    @error('bank_name') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.bank_accounts.mfo') }}</label>
                    <input type="text" wire:model="mfo" class="eg-input eg-mono" maxlength="5" placeholder="00401">
                    @error('mfo') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.bank_accounts.account') }}</label>
                    <input type="text" wire:model="account_number" class="eg-input eg-mono" maxlength="20">
                    @error('account_number') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.bank_accounts.label') }}</label>
                    <input type="text" wire:model="label" class="eg-input" placeholder="{{ __('cabinet.bank_accounts.label_hint') }}">
                </div>
                <div class="sm:col-span-4">
                    <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.bank_accounts.submit') }}</button>
                </div>
            </form>
        @endif

        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.bank_accounts.bank') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.bank_accounts.account') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.bank_accounts.mfo') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3">
                            <span class="font-medium">{{ $account->bank_name }}</span>
                            @if ($account->label)
                                <span class="block text-xs text-eg-muted">{{ $account->label }}</span>
                            @endif
                        </td>
                        <td class="eg-mono px-5 py-3">{{ $account->maskedNumber() }}</td>
                        <td class="eg-mono px-5 py-3 text-eg-muted">{{ $account->mfo }}</td>
                        <td class="px-5 py-3">
                            <x-eg.badge :color="$account->status->color()">{{ $account->status->label() }}</x-eg.badge>
                            @if ($account->is_primary)
                                <span class="ml-2 rounded-pill bg-eg-blue/10 px-2 py-0.5 text-xs font-semibold text-eg-blue">
                                    {{ __('cabinet.bank_accounts.primary') }}
                                </span>
                            @endif
                            @if ($account->rejection_reason)
                                <span class="block text-xs text-eg-danger">{{ $account->rejection_reason }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            @if ($account->canReceive() && ! $account->is_primary)
                                <button type="button" wire:click="makePrimary({{ $account->id }})"
                                        wire:confirm="{{ __('cabinet.bank_accounts.confirm_primary') }}"
                                        class="text-xs font-semibold text-eg-blue hover:underline">
                                    {{ __('cabinet.bank_accounts.make_primary') }}
                                </button>
                            @endif
                            @if (! $account->is_primary && $account->status->value !== 'archived')
                                <button type="button" wire:click="archive({{ $account->id }})"
                                        class="ml-3 text-xs text-eg-muted hover:text-eg-danger">
                                    {{ __('cabinet.bank_accounts.archive') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.bank_accounts.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
