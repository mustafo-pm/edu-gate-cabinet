<?php

declare(strict_types=1);

namespace App\Livewire\Psp;

use App\Models\Deposit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('partner.layout')]
#[Title('Deposit ledger')]
class Deposits extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.psp.deposits', [
            'deposits' => Deposit::latest('id')->paginate(15),
            'balance' => auth('psp')->user()->psp->depositBalance(),
        ]);
    }
}
