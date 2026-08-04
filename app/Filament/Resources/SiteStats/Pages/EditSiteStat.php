<?php

namespace App\Filament\Resources\SiteStats\Pages;

use App\Filament\Resources\SiteStats\SiteStatResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiteStat extends EditRecord
{
    protected static string $resource = SiteStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
