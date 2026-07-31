<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-64">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('cabinet.ui.search_students') }}" class="eg-input">
        </div>
        <select wire:model.live="department" class="eg-input max-w-48">
            <option value="">{{ __('cabinet.ui.all_departments') }}</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
        <button wire:click="$set('showImport', true)" class="eg-btn eg-btn--outline">{{ __('cabinet.students.import') }}</button>
        <button wire:click="$set('showCreate', true)" class="eg-btn eg-btn--primary">{{ __('cabinet.students.add') }}</button>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-card border border-eg-border bg-eg-card shadow-eg-sm">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-eg-muted">
                <tr class="border-b border-eg-border">
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.students.student_id') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.students.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.students.department') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.students.parent_phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('cabinet.schedules.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr class="border-b border-eg-border/60 last:border-0">
                        <td class="px-5 py-3"><span class="eg-mono text-eg-muted">{{ $student->student_id_number }}</span></td>
                        <td class="px-5 py-3 font-medium">{{ $student->fullName() }}</td>
                        <td class="px-5 py-3 text-eg-text">{{ $student->department?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-eg-text">{{ $student->parent_phone ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <x-eg.badge :color="$student->status->color()">{{ $student->status->label() }}</x-eg.badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-eg-muted">{{ __('cabinet.students.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>

    {{-- Create drawer --}}
    @if ($showCreate)
        <div class="fixed inset-0 z-40 flex justify-end bg-eg-navy/40" wire:click.self="$set('showCreate', false)">
            <div class="h-full w-full max-w-md overflow-y-auto bg-eg-card p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ __('cabinet.students.add') }}</h2>
                    <button wire:click="$set('showCreate', false)" class="text-eg-muted hover:text-eg-ink">✕</button>
                </div>
                <form wire:submit="save" class="mt-6 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.students.student_id') }}</label>
                        <input type="text" wire:model="student_id_number" class="eg-input">
                        @error('student_id_number') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.students.last_name') }}</label>
                            <input type="text" wire:model="last_name" class="eg-input">
                            @error('last_name') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.students.first_name') }}</label>
                            <input type="text" wire:model="first_name" class="eg-input">
                            @error('first_name') <p class="mt-1 text-xs text-eg-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.students.middle_name') }}</label>
                        <input type="text" wire:model="middle_name" class="eg-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.students.department') }}</label>
                        <select wire:model="department_id" class="eg-input">
                            <option value="">—</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold">{{ __('cabinet.students.parent_phone') }}</label>
                        <input type="text" wire:model="parent_phone" class="eg-input">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.ui.save') }}</button>
                        <button type="button" wire:click="$set('showCreate', false)" class="eg-btn eg-btn--outline">{{ __('cabinet.ui.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Import drawer --}}
    @if ($showImport)
        <div class="fixed inset-0 z-40 flex justify-end bg-eg-navy/40" wire:click.self="$set('showImport', false)">
            <div class="h-full w-full max-w-md overflow-y-auto bg-eg-card p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ __('cabinet.students.import_title') }}</h2>
                    <button wire:click="$set('showImport', false)" class="text-eg-muted hover:text-eg-ink">✕</button>
                </div>
                <p class="mt-2 text-sm text-eg-muted">{{ __('cabinet.students.import_hint') }}</p>
                <form wire:submit="import" class="mt-6 space-y-4">
                    <input type="file" wire:model="csv" accept=".csv,text/csv" class="eg-input">
                    @error('csv') <p class="text-xs text-eg-danger">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="csv" class="text-sm text-eg-muted">{{ __('cabinet.students.uploading') }}</div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="eg-btn eg-btn--primary">{{ __('cabinet.students.import') }}</button>
                        <button type="button" wire:click="$set('showImport', false)" class="eg-btn eg-btn--outline">{{ __('cabinet.ui.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
