<?php

namespace App\Filament\Resources\SiteStats\Pages;

use App\Filament\Resources\SiteStats\SiteStatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteStats extends ListRecords
{
    protected static string $resource = SiteStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
