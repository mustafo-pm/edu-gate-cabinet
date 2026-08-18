<?php

namespace App\Filament\Resources\MerchantBankAccounts\Pages;

use App\Filament\Resources\MerchantBankAccounts\MerchantBankAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerchantBankAccounts extends ListRecords
{
    protected static string $resource = MerchantBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add account')];
    }
}
