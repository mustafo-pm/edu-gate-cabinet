<?php

namespace App\Filament\Resources\SettlementAccounts\Pages;

use App\Filament\Resources\SettlementAccounts\SettlementAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSettlementAccounts extends ListRecords
{
    protected static string $resource = SettlementAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
