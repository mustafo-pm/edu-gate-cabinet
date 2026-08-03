<?php

namespace App\Filament\Resources\BankTransfers\Pages;

use App\Filament\Resources\BankTransfers\BankTransferResource;
use Filament\Resources\Pages\ListRecords;

class ListBankTransfers extends ListRecords
{
    protected static string $resource = BankTransferResource::class;

    // Read-only: transfers are created by the system, never by hand.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
