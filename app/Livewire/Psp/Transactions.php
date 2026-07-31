<?php

declare(strict_types=1);

namespace App\Livewire\Psp;

use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('partner.layout')]
#[Title('Transactions')]
class Transactions extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.psp.transactions', [
            'transactions' => Transaction::with('merchant')->latest('id')->paginate(15),
        ]);
    }
}
