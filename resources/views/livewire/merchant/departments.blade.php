<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-card border border-eg-border bg-eg-card px-5 py-3 text-sm shadow-eg-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <div class="flex items-center justify-between gap-4 border-b border-eg-border/60 px-5 py-4">
            <div>
                <h2 class="font-bold">{{ __('cabinet.departments.title') }}</h2>
                <p class="mt-0.5 text-sm text-eg-muted">{{ __('cabinet.departments.subtitle') }}</p>
            </div>
            <button type="button" wire:click="startAdding" class="eg-btn eg-btn--ghost shrink-0">
                {{ __('cabinet.departments.add') }}
            </button>
        </div>

        @if ($adding)
            <form wire:submit="save" class="grid gap-4 border-b border-eg-border/60 bg-eg-surface-2 p-5 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.departments.name') }}</label>
                    <input type="text" wire:model="name" class="eg-input">
                    @error('name') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.departments.code') }}</label>
                    <input type="text" wire:model="code" class="eg-input eg-mono" placeholder="ECON">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-eg-muted">{{ __('cabinet.departments.parent') }}</label>
                    <select wire:model="parent_id" class="eg-input">
                        <option value="">{{ __('cabinet.departments.no_parent') }}</option>
                        @foreach ($parents as $option)
                            <option value="{{ $option->id }}">{{ $option->path() }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.ui.save') }}</button>
                    <button type="button" wire:click="cancel" class="text-xs text-eg-muted hover:text-eg-ink">
                        {{ __('cabinet.ui.close') }}
                    </button>
                </div>
            </form>
        @endif

        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.departments.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.departments.code') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.nav.students') }}</th>
                    {{-- The reason this screen exists: accounting can see which
                         faculty is behind without exporting anything. --}}
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.departments.collected') }}</th>
                    <th class="px-5 py-3 text-right font-semibold">{{ __('cabinet.departments.outstanding') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    @php
                        $t = $totals[$department->id] ?? ['students' => 0, 'billed' => 0, 'paid' => 0];
                        $outstanding = max(0, $t['billed'] - $t['paid']);
                    @endphp
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3">
                            @if ($department->parent_id)
                                <span class="text-eg-muted">{{ $department->parent?->name }} ·</span>
                            @endif
                            <span class="font-medium">{{ $department->name }}</span>
                        </td>
                        <td class="eg-mono px-5 py-3 text-eg-muted">{{ $department->code ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">{{ $t['students'] }}</td>
                        <td class="px-5 py-3 text-right">{{ \App\Support\Money::format($t['paid']) }}</td>
                        <td class="px-5 py-3 text-right {{ $outstanding > 0 ? 'font-semibold text-eg-warning' : 'text-eg-muted' }}">
                            {{ \App\Support\Money::format($outstanding) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <button type="button" wire:click="edit({{ $department->id }})"
                                    class="text-xs font-semibold text-eg-blue hover:underline">
                                {{ __('cabinet.departments.edit') }}
                            </button>
                            @unless ($department->isInUse())
                                <button type="button" wire:click="delete({{ $department->id }})"
                                        wire:confirm="{{ __('cabinet.departments.confirm_delete') }}"
                                        class="ml-3 text-xs text-eg-muted hover:text-eg-danger">
                                    {{ __('cabinet.ui.remove') }}
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.departments.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-eg-muted">{{ __('cabinet.departments.hint') }}</p>
</div>
