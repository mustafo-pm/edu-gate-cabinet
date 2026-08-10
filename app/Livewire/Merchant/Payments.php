<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Models\PaymentReceipt;
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

    /** Link shown in the copy dialog, set when a row's button is pressed. */
    public ?string $receiptUrl = null;

    public ?string $receiptNumber = null;

    /**
     * Issue (or fetch) the receipt for one payment and surface its link.
     *
     * Receipts are created on demand rather than up front, so payments made
     * before this feature existed get one the first time anybody asks.
     */
    public function receipt(int $transactionId): void
    {
        // The tenant scope on Transaction keeps one institution from opening
        // another's receipt by editing the id in the request.
        $transaction = Transaction::findOrFail($transactionId);

        $receipt = PaymentReceipt::forTransaction($transaction);

        $this->receiptUrl = $receipt->url();
        $this->receiptNumber = $receipt->number;
    }

    public function closeReceipt(): void
    {
        $this->reset(['receiptUrl', 'receiptNumber']);
    }

    public function render()
    {
        $transactions = Transaction::query()
            ->with(['student', 'psp', 'receipt'])
            ->latest('id')
            ->paginate(15);

        return view('livewire.merchant.payments', ['transactions' => $transactions]);
    }
}
