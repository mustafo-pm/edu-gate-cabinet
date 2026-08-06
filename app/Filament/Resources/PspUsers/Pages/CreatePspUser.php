<?php

namespace App\Filament\Resources\PspUsers\Pages;

use App\Filament\Resources\Access\CabinetUsers;
use App\Filament\Resources\PspUsers\PspUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePspUser extends CreateRecord
{
    protected static string $resource = PspUserResource::class;

    /**
     * The form has no password field. Saving with a random unusable value
     * means a create that fails halfway can never leave an account holding a
     * guessable credential; afterCreate replaces it with the real temporary
     * one and shows it once.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = Hash::make(Str::random(64));
        $data['must_change_password'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        CabinetUsers::afterCreate($this->record);
    }
}
