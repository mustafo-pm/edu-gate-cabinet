<div>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('cabinet.ui.search_by_student') }}" class="eg-input flex-1 min-w-64">
        <select wire:model.live="status" class="eg-input max-w-48">
            <option value="">{{ __('cabinet.ui.all_statuses') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.schedules.student') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.schedules.title') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.schedules.amount') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.schedules.paid') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.schedules.outstanding') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.schedules.due') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.schedules.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $s)
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3 font-medium">{{ $s->student?->fullName() ?? '—' }}</td>
                        <td class="px-5 py-3 text-eg-text">{{ $s->title }}</td>
                        <td class="px-5 py-3 text-right">{{ \App\Support\Money::format($s->amount) }}</td>
                        <td class="px-5 py-3 text-right text-eg-success">{{ \App\Support\Money::format($s->paid_amount) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ \App\Support\Money::format($s->outstanding()) }}</td>
                        <td class="px-5 py-3 text-eg-muted">{{ $s->due_date?->format('d M Y') }}</td>
                        <td class="px-5 py-3">
                            <x-eg.badge :color="$s->status->color()">{{ $s->status->label() }}</x-eg.badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.schedules.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $schedules->links() }}</div>
</div>
