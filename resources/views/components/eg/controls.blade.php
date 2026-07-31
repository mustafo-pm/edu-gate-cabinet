@props(['onDark' => false])

@php
    $locales = config('localization.supported', []);
    $current = $locales[app()->getLocale()] ?? ['native' => app()->getLocale(), 'flag' => '🌐'];
    $trigger = $onDark
        ? 'text-white/80 hover:bg-white/10'
        : 'text-eg-text hover:bg-eg-surface2';
    $menu = 'absolute right-0 z-50 mt-2 overflow-hidden rounded-xl border border-eg-border bg-eg-card p-1 shadow-eg-lg';
@endphp

<div class="flex items-center gap-1">
    {{-- Theme: Light / Dark / System --}}
    <details class="eg-dd relative">
        <summary class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg transition {{ $trigger }}" aria-label="{{ __('cabinet.ui.theme') }}">
            {{-- sun in light, moon in dark --}}
            <svg class="h-[18px] w-[18px] dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            <svg class="hidden h-[18px] w-[18px] dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </summary>
        <div class="{{ $menu }} w-44">
            @php
                $opts = [
                    ['light',  __('cabinet.ui.light'),  '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>'],
                    ['dark',   __('cabinet.ui.dark'),   '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'],
                    ['system', __('cabinet.ui.system'), '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'],
                ];
            @endphp
            @foreach ($opts as [$mode, $label, $svg])
                <button type="button" data-eg-theme-opt="{{ $mode }}" onclick="egSetTheme('{{ $mode }}')" aria-current="false"
                        class="eg-theme-opt flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-eg-text transition hover:bg-eg-surface2">
                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $svg !!}</svg>
                    <span>{{ $label }}</span>
                    <svg class="eg-check ml-auto h-4 w-4 text-eg-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
            @endforeach
        </div>
    </details>

    {{-- Locale switcher --}}
    <details class="eg-dd relative">
        <summary class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-lg px-2.5 text-sm font-medium transition {{ $trigger }}">
            <span>{{ $current['flag'] ?? '🌐' }}</span>
            <span class="hidden sm:inline">{{ $current['native'] }}</span>
            <span class="text-xs opacity-60">▾</span>
        </summary>
        <div class="{{ $menu }} w-48">
            @foreach ($locales as $code => $meta)
                <a href="{{ route('locale.switch', $code) }}"
                   class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm text-eg-text hover:bg-eg-surface2
                          {{ app()->getLocale() === $code ? 'font-semibold text-eg-blue' : '' }}">
                    <span>{{ $meta['flag'] }}</span>
                    <span>{{ $meta['native'] }}</span>
                    @if (app()->getLocale() === $code)
                        <span class="ml-auto text-eg-blue">✓</span>
                    @endif
                </a>
            @endforeach
        </div>
    </details>
</div>
