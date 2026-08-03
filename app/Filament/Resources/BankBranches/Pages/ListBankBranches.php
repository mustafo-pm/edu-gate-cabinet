<?php

namespace App\Filament\Resources\BankBranches\Pages;

use App\Filament\Resources\BankBranches\BankBranchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankBranches extends ListRecords
{
    protected static string $resource = BankBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
