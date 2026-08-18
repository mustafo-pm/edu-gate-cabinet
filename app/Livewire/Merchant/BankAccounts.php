<?php

declare(strict_types=1);

namespace App\Livewire\Merchant;

use App\Enums\MerchantBankAccountStatus;
use App\Models\Bank;
use App\Models\MerchantBankAccount;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The institution's bank accounts.
 *
 * On its own page rather than buried in the profile, because this is the one
 * screen in the merchant cabinet where a change moves money. An institution
 * may add and retire accounts; only EduGate can approve one, and only an
 * approved account can be made primary.
 */
#[Layout('merchant.layout')]
class BankAccounts extends Component
{
    public bool $adding = false;

    public string $label = '';

    public string $bank_name = '';

    public string $mfo = '';

    public string $account_number = '';

    public function add(): void
    {
        $data = $this->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'mfo' => ['required', 'string', 'regex:/^\d{5}$/'],
            'account_number' => ['required', 'string', 'regex:/^\d{20}$/'],
        ]);

        // Uniqueness is per institution, so two universities may legitimately
        // hold the same account number at the same bank.
        $exists = MerchantBankAccount::where('mfo', $data['mfo'])
            ->where('account_number', $data['account_number'])
            ->exists();

        if ($exists) {
            $this->addError('account_number', __('cabinet.bank_accounts.duplicate'));

            return;
        }

        MerchantBankAccount::create($data + [
            // Never trusted straight from the form. Nothing is paid here until
            // EduGate has checked it against the institution's documents.
            'status' => MerchantBankAccountStatus::Pending,
            'bank_id' => Bank::where('code', $data['mfo'])->value('id'),
        ]);

        $this->reset(['label', 'bank_name', 'mfo', 'account_number', 'adding']);
        session()->flash('status', __('cabinet.bank_accounts.submitted'));
    }

    /** Choose which approved account settlements are sent to. */
    public function makePrimary(int $id): void
    {
        MerchantBankAccount::findOrFail($id)->makePrimary();

        session()->flash('status', __('cabinet.bank_accounts.primary_changed'));
    }

    /**
     * Retire an account. Never deleted — past postings name it, and a row that
     * can vanish takes the answer to "where did last term's money go" with it.
     */
    public function archive(int $id): void
    {
        $account = MerchantBankAccount::findOrFail($id);

        if ($account->is_primary) {
            session()->flash('status', __('cabinet.bank_accounts.cannot_archive_primary'));

            return;
        }

        $account->update(['status' => MerchantBankAccountStatus::Archived, 'is_primary' => false]);
        session()->flash('status', __('cabinet.bank_accounts.archived'));
    }

    public function render()
    {
        return view('livewire.merchant.bank-accounts', [
            'accounts' => MerchantBankAccount::orderByDesc('is_primary')->orderBy('id')->get(),
            'primary' => auth('merchant')->user()->merchant->primaryBankAccount(),
        ])->title(__('cabinet.bank_accounts.title'));
    }
}
