<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('merchant.layout')]
class Departments extends Component
{
    // UI demo page — no backend logic yet (see the on-page demo notice).
    public function render()
    {
        return view('livewire.merchant.departments')->title(__('ext.dept.title'));
    }
}
