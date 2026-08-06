<?php

namespace App\Filament\Resources\PspUsers\Pages;

use App\Filament\Resources\PspUsers\PspUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPspUsers extends ListRecords
{
    protected static string $resource = PspUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New account')];
    }
}
