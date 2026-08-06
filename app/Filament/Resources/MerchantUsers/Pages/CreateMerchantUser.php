<?php

namespace App\Filament\Resources\MerchantUsers\Pages;

use App\Filament\Resources\Access\CabinetUsers;
use App\Filament\Resources\MerchantUsers\MerchantUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateMerchantUser extends CreateRecord
{
    protected static string $resource = MerchantUserResource::class;

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
