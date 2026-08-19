<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-card border border-eg-border bg-eg-card px-5 py-3 text-sm shadow-eg-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- One-time password reveal. Handed over in person or by phone: an email
         or a chat keeps its history long after the account is gone. --}}
    @if ($issuedPassword)
        <div class="rounded-card border border-eg-blue/30 bg-eg-blue/5 p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-bold text-eg-blue">{{ __('cabinet.staff.password_title') }}</p>
                    <p class="mt-1 text-sm text-eg-text">{{ __('cabinet.staff.password_hint', ['email' => $issuedFor]) }}</p>
                </div>
                <button wire:click="dismissPassword" class="text-eg-muted hover:text-eg-ink">✕</button>
            </div>
            <p class="eg-mono mt-4 break-all rounded bg-eg-surface-2 px-3 py-2 text-sm">{{ $issuedPassword }}</p>
        </div>
    @endif

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <div class="flex items-center justify-between gap-4 border-b border-eg-border/60 px-5 py-4">
            <div>
                <h2 class="font-bold">{{ __('cabinet.staff.title') }}</h2>
                <p class="mt-0.5 text-sm text-eg-muted">{{ __('cabinet.staff.subtitle') }}</p>
            </div>
            <button type="button" wire:click="$toggle('inviting')" class="eg-btn eg-btn--ghost shrink-0">
                {{ __('cabinet.staff.invite') }}
            </button>
        </div>

        @if ($inviting)
            <form wire:submit="invite" class="grid gap-4 border-b border-eg-border/60 bg-eg-surface-2 p-5 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.staff.name') }}</label>
                    <input type="text" wire:model="name" class="eg-input">
                    @error('name') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.staff.email') }}</label>
                    <input type="email" wire:model="email" class="eg-input">
                    @error('email') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.staff.phone') }}</label>
                    <input type="text" wire:model="phone" class="eg-input">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.staff.role') }}</label>
                    <select wire:model="role" class="eg-input">
                        @foreach ($roles as $r)
                            {{-- Only an owner may create another owner. --}}
                            @if ($r !== 'owner' || $isOwner)
                                <option value="{{ $r }}">{{ __('cabinet.staff.role_'.$r) }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-4">
                    <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.staff.create') }}</button>
                    <span class="ml-3 text-xs text-eg-muted">{{ __('cabinet.staff.create_hint') }}</span>
                </div>
            </form>
        @endif

        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.staff.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.staff.role') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.payments.status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($staff as $member)
                    @php $memberRole = $member->roles->first()?->name; @endphp
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3">
                            <span class="font-medium">{{ $member->name }}</span>
                            @if ($member->is($me))
                                <span class="ml-1 text-xs text-eg-muted">({{ __('cabinet.staff.you') }})</span>
                            @endif
                            <span class="block text-xs text-eg-muted">{{ $member->email }}</span>
                        </td>

                        <td class="px-5 py-3">
                            {{-- Your own role is shown, never offered: nobody
                                 promotes themselves. --}}
                            @if ($member->is($me) || ($memberRole === 'owner' && ! $isOwner))
                                <span class="text-eg-text">{{ $memberRole ? __('cabinet.staff.role_'.$memberRole) : '—' }}</span>
                            @else
                                <select class="eg-input !h-8 !py-0 text-xs"
                                        wire:change="changeRole({{ $member->id }}, $event.target.value)">
                                    @foreach ($roles as $r)
                                        @if ($r !== 'owner' || $isOwner)
                                            <option value="{{ $r }}" @selected($memberRole === $r)>
                                                {{ __('cabinet.staff.role_'.$r) }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            @endif
                        </td>

                        <td class="px-5 py-3">
                            <x-eg.badge :color="$member->is_active ? 'success' : 'danger'">
                                {{ $member->is_active ? __('cabinet.staff.active') : __('cabinet.staff.disabled') }}
                            </x-eg.badge>
                            @if ($member->must_change_password)
                                <span class="ml-2 text-xs text-eg-muted">{{ __('cabinet.staff.must_change') }}</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            @unless ($member->is($me))
                                <button type="button" wire:click="resetPassword({{ $member->id }})"
                                        wire:confirm="{{ __('cabinet.staff.confirm_reset') }}"
                                        class="text-xs font-semibold text-eg-blue hover:underline">
                                    {{ __('cabinet.staff.reset_password') }}
                                </button>
                                <button type="button" wire:click="toggleActive({{ $member->id }})"
                                        class="ml-3 text-xs text-eg-muted hover:text-eg-danger">
                                    {{ $member->is_active ? __('cabinet.staff.disable') : __('cabinet.staff.enable') }}
                                </button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- What each role can actually do, on the screen where it is chosen. --}}
    <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
        <h2 class="font-bold">{{ __('cabinet.staff.what_roles_do') }}</h2>
        <dl class="mt-3 space-y-2 text-sm">
            @foreach ($roles as $r)
                <div class="flex gap-3">
                    <dt class="w-28 shrink-0 font-medium">{{ __('cabinet.staff.role_'.$r) }}</dt>
                    <dd class="text-eg-muted">{{ __('cabinet.staff.role_'.$r.'_desc') }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
