<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\ScheduleStatus;
use App\Models\Department;
use App\Models\PaymentSchedule;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Faculties and chairs.
 *
 * A department earns its own screen because of what hangs off it, not because
 * a student needs a label: an institution's accounting asks "what has the
 * economics faculty collected and what is still owed", and that question has
 * no answer unless students are grouped.
 *
 * The tree is two useful levels in practice — faculty, then chair — though the
 * schema does not cap it. What it does cap is cycles: a department cannot sit
 * under itself or under one of its own children.
 */
#[Layout('merchant.layout')]
class Departments extends Component
{
    public bool $adding = false;

    public ?int $editing = null;

    public string $name = '';

    public string $code = '';

    public ?string $parent_id = null;

    public function startAdding(): void
    {
        $this->reset(['name', 'code', 'parent_id', 'editing']);
        $this->adding = true;
    }

    public function edit(int $id): void
    {
        $department = Department::findOrFail($id);

        $this->editing = $department->id;
        $this->name = (string) $department->name;
        $this->code = (string) $department->code;
        $this->parent_id = $department->parent_id ? (string) $department->parent_id : null;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', Rule::exists('departments', 'id')],
        ]);

        $parentId = $data['parent_id'] ? (int) $data['parent_id'] : null;

        if ($this->editing) {
            $department = Department::findOrFail($this->editing);

            // A department under itself, or under one of its own children,
            // makes every walk of the tree loop forever.
            if ($parentId !== null && in_array($parentId, $department->descendantIds(), true)) {
                $this->addError('parent_id', __('cabinet.departments.cycle'));

                return;
            }

            $department->update([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'parent_id' => $parentId,
            ]);
        } else {
            Department::create([
                'name' => $data['name'],
                'code' => $data['code'] ?: null,
                'parent_id' => $parentId,
            ]);   // merchant_id set by the tenant scope
        }

        $this->cancel();
        session()->flash('status', __('cabinet.departments.saved'));
    }

    public function cancel(): void
    {
        $this->reset(['name', 'code', 'parent_id', 'editing', 'adding']);
    }

    /**
     * Removing a department is refused while anything hangs off it.
     *
     * Cascading would take the students with it, and nulling their department
     * loses the grouping silently — either way somebody discovers it a term
     * later. Reassigning first is the only honest order.
     */
    public function delete(int $id): void
    {
        $department = Department::findOrFail($id);

        if ($department->isInUse()) {
            session()->flash('status', __('cabinet.departments.in_use'));

            return;
        }

        $department->delete();
        session()->flash('status', __('cabinet.departments.deleted'));
    }

    public function render()
    {
        $departments = Department::with('parent')->orderBy('name')->get();

        return view('livewire.merchant.departments', [
            'departments' => $departments,
            'totals' => $this->totals(),
            // A department cannot be its own parent, nor sit under its own
            // children — so those are never offered while editing.
            'parents' => $this->editing
                ? $departments->whereNotIn('id', Department::findOrFail($this->editing)->descendantIds())
                : $departments,
        ])->title(__('cabinet.departments.title'));
    }

    /**
     * Students, money owed and money collected, per department.
     *
     * One grouped query rather than a count per row: an institution with forty
     * chairs would otherwise fire forty-one.
     *
     * @return array<int, array{students: int, billed: int, paid: int}>
     */
    private function totals(): array
    {
        return PaymentSchedule::query()
            ->join('students', 'students.id', '=', 'payment_schedules.student_id')
            ->whereNotNull('students.department_id')
            ->where('payment_schedules.status', '!=', ScheduleStatus::Cancelled->value)
            ->groupBy('students.department_id')
            ->selectRaw('students.department_id as did')
            ->selectRaw('count(distinct students.id) as students')
            ->selectRaw('sum(payment_schedules.amount) as billed')
            ->selectRaw('sum(payment_schedules.paid_amount) as paid')
            ->get()
            ->keyBy('did')
            ->map(fn ($row) => [
                'students' => (int) $row->students,
                'billed' => (int) $row->billed,
                'paid' => (int) $row->paid,
            ])
            ->all();
    }
}
