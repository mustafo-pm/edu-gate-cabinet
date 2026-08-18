<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-card border border-eg-border bg-eg-card px-5 py-3 text-sm shadow-eg-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Identity ------------------------------------------------------- --}}
    <div class="rounded-card border border-eg-border bg-eg-card p-6 shadow-eg-sm">
        <h2 class="font-bold">{{ __('cabinet.profile.identity') }}</h2>
        <p class="mt-1 text-sm text-eg-muted">{{ __('cabinet.profile.identity_hint') }}</p>

        <form wire:submit="save" class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.name_uz') }}</label>
                <input type="text" wire:model="name_uz" class="eg-input">
                @error('name_uz') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.name_ru') }}</label>
                <input type="text" wire:model="name_ru" class="eg-input">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.name_en') }}</label>
                <input type="text" wire:model="name_en" class="eg-input">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.legal_name') }}</label>
                <input type="text" wire:model="legal_name" class="eg-input">
                <p class="mt-1 text-xs text-eg-muted">{{ __('cabinet.profile.legal_name_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.stir') }}</label>
                <input type="text" wire:model="stir" class="eg-input eg-mono">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.website') }}</label>
                <input type="url" wire:model="website_url" class="eg-input" placeholder="https://">
                @error('website_url') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium">{{ __('cabinet.profile.address') }}</label>
                <input type="text" wire:model="address" class="eg-input">
            </div>

            <label class="flex items-start gap-2 text-sm sm:col-span-2">
                <input type="checkbox" wire:model="show_on_receipt" class="mt-0.5 rounded border-eg-border">
                <span>
                    {{ __('cabinet.profile.show_on_receipt') }}
                    <span class="block text-xs text-eg-muted">{{ __('cabinet.profile.show_on_receipt_hint') }}</span>
                </span>
            </label>

            <div class="sm:col-span-2">
                <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.ui.save') }}</button>
            </div>
        </form>
    </div>

    {{-- Branding ------------------------------------------------------- --}}
    <div class="rounded-card border border-eg-border bg-eg-card p-6 shadow-eg-sm">
        <h2 class="font-bold">{{ __('cabinet.profile.branding') }}</h2>
        <p class="mt-1 text-sm text-eg-muted">{{ __('cabinet.profile.branding_hint') }}</p>

        <form wire:submit="saveLogos" class="mt-5 space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                {{-- Two logos, previewed on the background each is meant for:
                     a mark drawn for white paper vanishes on a dark screen. --}}
                <div>
                    <label class="mb-2 block text-sm font-medium">{{ __('cabinet.profile.logo_light') }}</label>
                    <div class="flex h-24 items-center justify-center rounded-lg border border-eg-border bg-white p-3">
                        @if ($merchant->logoUrl())
                            <img src="{{ $merchant->logoUrl() }}" alt="" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-xs text-eg-muted">{{ __('cabinet.profile.no_logo') }}</span>
                        @endif
                    </div>
                    <input type="file" wire:model="logoLight" class="eg-input mt-2" accept="image/png,image/jpeg,image/webp">
                    @error('logoLight') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                    @if ($merchant->logo_light_path)
                        <button type="button" wire:click="removeLogo('light')" class="mt-1 text-xs text-eg-muted hover:text-eg-danger">
                            {{ __('cabinet.ui.remove') }}
                        </button>
                    @endif
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">{{ __('cabinet.profile.logo_dark') }}</label>
                    <div class="flex h-24 items-center justify-center rounded-lg border border-eg-border bg-eg-navy p-3">
                        @if ($merchant->logoUrl(dark: true))
                            <img src="{{ $merchant->logoUrl(dark: true) }}" alt="" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-xs text-white/50">{{ __('cabinet.profile.no_logo') }}</span>
                        @endif
                    </div>
                    <input type="file" wire:model="logoDark" class="eg-input mt-2" accept="image/png,image/jpeg,image/webp">
                    @error('logoDark') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                    @if ($merchant->logo_dark_path)
                        <button type="button" wire:click="removeLogo('dark')" class="mt-1 text-xs text-eg-muted hover:text-eg-danger">
                            {{ __('cabinet.ui.remove') }}
                        </button>
                    @endif
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">{{ __('cabinet.profile.banner') }}</label>
                    <div class="flex h-24 items-center justify-center overflow-hidden rounded-lg border border-eg-border bg-eg-surface-2">
                        @if ($merchant->bannerUrl())
                            <img src="{{ $merchant->bannerUrl() }}" alt="" class="h-full w-full object-cover">
                        @else
                            <span class="text-xs text-eg-muted">{{ __('cabinet.profile.no_banner') }}</span>
                        @endif
                    </div>
                    <input type="file" wire:model="banner" class="eg-input mt-2" accept="image/png,image/jpeg,image/webp">
                    @error('banner') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                    @if ($merchant->banner_path)
                        <button type="button" wire:click="removeLogo('banner')" class="mt-1 text-xs text-eg-muted hover:text-eg-danger">
                            {{ __('cabinet.ui.remove') }}
                        </button>
                    @endif
                </div>
            </div>

            <button type="submit" class="eg-btn eg-btn--primary" wire:loading.attr="disabled">
                {{ __('cabinet.profile.upload') }}
            </button>
        </form>
    </div>

    {{-- Contacts ------------------------------------------------------- --}}
    <div class="rounded-card border border-eg-border bg-eg-card p-6 shadow-eg-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold">{{ __('cabinet.profile.contacts') }}</h2>
                <p class="mt-1 text-sm text-eg-muted">{{ __('cabinet.profile.contacts_hint') }}</p>
            </div>
            <button type="button" wire:click="addContact" class="eg-btn eg-btn--ghost shrink-0">
                {{ __('cabinet.profile.add_contact') }}
            </button>
        </div>

        <form wire:submit="saveContacts" class="mt-5 space-y-4">
            @forelse ($contacts as $i => $contact)
                <div class="grid items-start gap-3 rounded-lg border border-eg-border/60 p-4 sm:grid-cols-6" wire:key="contact-{{ $i }}">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.profile.department') }}</label>
                        <select wire:model="contacts.{{ $i }}.kind" class="eg-input">
                            @foreach ($kinds as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="contacts.{{ $i }}.title" class="eg-input mt-2"
                               placeholder="{{ __('cabinet.profile.custom_title') }}">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.profile.person') }}</label>
                        <input type="text" wire:model="contacts.{{ $i }}.person_name" class="eg-input">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.profile.phone') }}</label>
                        <input type="text" wire:model="contacts.{{ $i }}.phone" class="eg-input">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.profile.email') }}</label>
                        <input type="email" wire:model="contacts.{{ $i }}.email" class="eg-input">
                        @error("contacts.{$i}.email") <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" wire:model="contacts.{{ $i }}.is_public" class="rounded border-eg-border">
                            {{ __('cabinet.profile.public') }}
                        </label>
                        <button type="button" wire:click="removeContact({{ $i }})"
                                class="text-left text-xs text-eg-muted hover:text-eg-danger">
                            {{ __('cabinet.ui.remove') }}
                        </button>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-eg-muted">{{ __('cabinet.profile.no_contacts') }}</p>
            @endforelse

            @if ($contacts)
                <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.ui.save') }}</button>
            @endif
        </form>
    </div>
</div>
