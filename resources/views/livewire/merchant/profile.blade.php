@php
    $m = auth('merchant')->user()?->merchant;
    $locales = config('localization.supported', []);
@endphp

<div class="space-y-6">
    <x-eg.demo-banner />
    <p class="-mt-2 text-sm text-eg-muted">{{ __('ext.uni.subtitle') }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left column --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Names in 5 languages --}}
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <div class="mb-1 flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-eg-ink">{{ __('ext.uni.names') }}</h2>
                    <x-eg.badge color="processing">5</x-eg.badge>
                </div>
                <p class="mb-4 text-xs text-eg-muted">{{ __('ext.uni.names_hint') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($locales as $code => $meta)
                        <div>
                            <label class="mb-1 flex items-center gap-1.5 text-xs font-medium text-eg-muted">
                                <span>{{ $meta['flag'] }}</span> {{ $meta['native'] }}
                            </label>
                            <input type="text" class="eg-input" value="{{ $m?->name }}">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Details --}}
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <h2 class="mb-4 text-sm font-semibold text-eg-ink">{{ __('ext.uni.details') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.type') }}</label>
                        <select class="eg-input">
                            <option>{{ __('cabinet.status.active') }} · University</option>
                            <option>School</option>
                            <option>Kindergarten</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.stir') }}</label>
                        <input type="text" class="eg-input" value="{{ $m?->stir }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.bank') }}</label>
                        <input type="text" class="eg-input" value="{{ $m?->bank_account }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.mfo') }}</label>
                        <input type="text" class="eg-input" value="{{ $m?->mfo }}">
                    </div>
                </div>
            </div>

            {{-- Contact --}}
            <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
                <h2 class="mb-4 text-sm font-semibold text-eg-ink">{{ __('ext.uni.contact') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.phone') }}</label>
                        <input type="text" class="eg-input" value="{{ $m?->contact_phone }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.email') }}</label>
                        <input type="email" class="eg-input" value="{{ $m?->contact_email }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('ext.uni.address') }}</label>
                        <input type="text" class="eg-input" value="Toshkent, Universitet koʻchasi 4">
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" class="eg-btn eg-btn--primary" onclick="return false">{{ __('ext.uni.save') }}</button>
            </div>
        </div>

        {{-- Logo column --}}
        <div class="rounded-card border border-eg-border bg-eg-card p-5 shadow-eg-sm">
            <h2 class="text-sm font-semibold text-eg-ink">{{ __('ext.uni.logo') }}</h2>
            <p class="mb-4 mt-1 text-xs text-eg-muted">{{ __('ext.uni.logo_hint') }}</p>
            <div class="space-y-3">
                @foreach ([['ext.uni.logo_small', 'h-10 w-10'], ['ext.uni.logo_medium', 'h-14 w-14'], ['ext.uni.logo_high', 'h-20 w-20']] as [$lbl, $size])
                    <div class="flex items-center gap-3 rounded-lg border border-dashed border-eg-border p-3">
                        <div class="grid {{ $size }} shrink-0 place-items-center rounded-lg bg-eg-surface2">
                            <img src="{{ asset('favicon.svg') }}" alt="" class="h-2/3 w-2/3">
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-eg-ink">{{ __($lbl) }}</p>
                            <p class="text-xs text-eg-muted">PNG, SVG</p>
                        </div>
                        <button type="button" class="eg-btn eg-btn--outline !h-8 !px-3 text-xs" onclick="return false">{{ __('ext.uni.replace') }}</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
