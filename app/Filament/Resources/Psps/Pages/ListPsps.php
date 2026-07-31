<?php

namespace App\Filament\Resources\Psps\Pages;

use App\Filament\Resources\Psps\PspResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPsps extends ListRecords
{
    protected static string $resource = PspResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
