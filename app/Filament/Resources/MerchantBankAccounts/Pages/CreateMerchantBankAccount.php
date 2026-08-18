<?php

namespace App\Filament\Resources\MerchantBankAccounts\Pages;

use App\Enums\MerchantBankAccountStatus;
use App\Filament\Resources\MerchantBankAccounts\MerchantBankAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchantBankAccount extends CreateRecord
{
    protected static string $resource = MerchantBankAccountResource::class;

    /**
     * An account entered here is approved on the spot.
     *
     * The approval step exists so that no account an INSTITUTION typed can
     * receive money until we have checked it. An admin typing it off the
     * contract is that check — asking them to then approve their own entry
     * would be a second click that verifies nothing.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mutateFormDataBeforeCreateForTesting($data);
    }

    /** @internal same logic, reachable from a test without reflection. */
    public function mutateFormDataBeforeCreateForTesting(array $data): array
    {
        return $data + [
            'status' => MerchantBankAccountStatus::Active->value,
            'approved_at' => now(),
            'approved_by' => auth('admin')->id(),
        ];
    }
}
