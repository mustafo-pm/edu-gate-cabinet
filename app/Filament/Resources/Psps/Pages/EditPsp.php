<?php

namespace App\Filament\Resources\Psps\Pages;

use App\Filament\Resources\Psps\PspResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPsp extends EditRecord
{
    protected static string $resource = PspResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
