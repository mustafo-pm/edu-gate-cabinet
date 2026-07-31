<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\StudentStatus;
use App\Models\Department;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('merchant.layout')]
#[Title('Students')]
class Students extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?string $department = null;

    // Create form
    public bool $showCreate = false;
    public string $student_id_number = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $middle_name = '';
    public ?string $department_id = null;
    public string $parent_phone = '';

    // Import
    public bool $showImport = false;
    public $csv;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'student_id_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'parent_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        // Guard uniqueness within this merchant (composite unique in DB).
        $exists = Student::where('student_id_number', $data['student_id_number'])->exists();
        if ($exists) {
            $this->addError('student_id_number', __('cabinet.students.exists'));

            return;
        }

        Student::create($data + ['status' => StudentStatus::Active]); // merchant_id auto-set by scope

        $this->reset(['student_id_number', 'first_name', 'last_name', 'middle_name', 'parent_phone', 'showCreate']);
        session()->flash('status', __('cabinet.students.added'));
    }

    public function import(): void
    {
        $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $rows = array_map('str_getcsv', file($this->csv->getRealPath()));
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?: []);

        $created = 0;
        DB::transaction(function () use ($rows, $header, &$created) {
            foreach ($rows as $row) {
                $data = array_combine($header, array_map('trim', $row + array_fill(0, count($header), '')));
                if (empty($data['student_id_number'] ?? null)) {
                    continue;
                }
                if (Student::where('student_id_number', $data['student_id_number'])->exists()) {
                    continue;
                }
                Student::create([
                    'student_id_number' => $data['student_id_number'],
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'middle_name' => $data['middle_name'] ?? null,
                    'parent_phone' => $data['parent_phone'] ?? null,
                    'status' => StudentStatus::Active,
                ]);
                $created++;
            }
        });

        $this->reset(['csv', 'showImport']);
        session()->flash('status', __('cabinet.students.imported', ['count' => $created]));
    }

    public function render()
    {
        $students = Student::query()
            ->with('department')
            ->when($this->search !== '', function ($q) {
                $term = "%{$this->search}%";
                $q->where(fn ($qq) => $qq
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('student_id_number', 'like', $term));
            })
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->orderBy('last_name')
            ->paginate(12);

        return view('livewire.merchant.students', [
            'students' => $students,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }
}
