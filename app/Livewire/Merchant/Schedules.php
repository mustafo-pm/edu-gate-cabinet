<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\ScheduleStatus;
use App\Models\PaymentSchedule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('merchant.layout')]
#[Title('Payment schedules')]
class Schedules extends Component
{
    use WithPagination;

    #[Url]
    public ?string $status = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $schedules = PaymentSchedule::query()
            ->with('student')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->whereHas('student', function ($s) {
                $term = "%{$this->search}%";
                $s->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term);
            }))
            ->orderBy('due_date')
            ->paginate(12);

        return view('livewire.merchant.schedules', [
            'schedules' => $schedules,
            'statuses' => ScheduleStatus::cases(),
        ]);
    }
}
