<?php

namespace App\Filament\Resources\SettlementAccounts\Pages;

use App\Filament\Resources\SettlementAccounts\SettlementAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSettlementAccount extends EditRecord
{
    protected static string $resource = SettlementAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
