<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    // Read-only: append-only transactions cannot be created here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
