<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('merchant.layout')]
#[Title('Payments')]
class Payments extends Component
{
    use WithPagination;

    public function render()
    {
        $transactions = Transaction::query()
            ->with(['student', 'psp'])
            ->latest('id')
            ->paginate(15);

        return view('livewire.merchant.payments', ['transactions' => $transactions]);
    }
}
