<?php

namespace App\Filament\Resources\BankBranches\Pages;

use App\Filament\Resources\BankBranches\BankBranchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBankBranch extends EditRecord
{
    protected static string $resource = BankBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
