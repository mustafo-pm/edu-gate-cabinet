{{-- A tasteful "this is a demo" notice. Uses the brand danger red. --}}
<div class="mb-6 flex items-start gap-3 rounded-xl border border-eg-danger/30 bg-eg-danger/[0.06] px-4 py-3.5">
    <span class="relative mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-eg-danger/40"></span>
        <span class="relative grid h-6 w-6 place-items-center rounded-full bg-eg-danger text-white">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5M12 16h.01"/></svg>
        </span>
    </span>
    <div class="min-w-0">
        <span class="inline-flex items-center rounded-full bg-eg-danger px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white">
            {{ __('ext.demo.badge') }}
        </span>
        <p class="mt-1.5 text-sm font-medium text-eg-danger">
            {{ $slot->isEmpty() ? __('ext.demo.note') : $slot }}
        </p>
    </div>
</div>
