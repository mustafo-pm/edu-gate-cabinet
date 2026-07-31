<div x-data="{ open: false }" @click.outside="open = false" class="relative" wire:poll.60s>
    {{-- Bell trigger --}}
    <button type="button" @click="open = !open" aria-label="{{ __('cabinet.notif.title') }}"
            class="relative grid h-9 w-9 place-items-center rounded-lg text-eg-text transition hover:bg-eg-surface2">
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
        </svg>
        @if ($unread > 0)
            <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-eg-danger px-1 text-[10px] font-bold leading-none text-white">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    {{-- Panel --}}
    <div x-show="open" x-cloak x-transition.origin.top.right
         class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-eg-border bg-eg-card shadow-eg-lg">
        <div class="flex items-center justify-between border-b border-eg-border px-4 py-3">
            <p class="text-sm font-semibold text-eg-ink">{{ __('cabinet.notif.title') }}</p>
            @if ($unread > 0)
                <button wire:click="markAllRead" class="text-xs font-medium text-eg-blue hover:underline">
                    {{ __('cabinet.notif.mark_all_read') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($notifications as $n)
                @php
                    $level = $n->data['level'] ?? 'info';
                    $dot = [
                        'success' => 'bg-eg-success',
                        'warning' => 'bg-eg-warning',
                        'danger'  => 'bg-eg-danger',
                    ][$level] ?? 'bg-eg-blue';
                    $isUnread = is_null($n->read_at);
                @endphp
                <button wire:click="markRead('{{ $n->id }}')" type="button"
                        class="flex w-full items-start gap-3 border-b border-eg-border/60 px-4 py-3 text-left transition last:border-0 hover:bg-eg-surface2
                               {{ $isUnread ? 'bg-eg-blue/5' : '' }}">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $isUnread ? $dot : 'bg-transparent' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-eg-ink">{{ $n->data['title'] ?? '' }}</span>
                        @if (!empty($n->data['message']))
                            <span class="mt-0.5 block text-xs text-eg-muted">{{ $n->data['message'] }}</span>
                        @endif
                        <span class="mt-1 block text-[11px] text-eg-muted">{{ $n->created_at?->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <div class="px-4 py-10 text-center">
                    <svg class="mx-auto h-8 w-8 text-eg-muted/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                    </svg>
                    <p class="mt-2 text-sm text-eg-muted">{{ __('cabinet.notif.empty') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
