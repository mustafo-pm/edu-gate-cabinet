<?php

namespace App\Filament\Resources\MerchantUsers\Pages;

use App\Filament\Resources\Access\CabinetUsers;
use App\Filament\Resources\MerchantUsers\MerchantUserResource;
use App\Models\MerchantUser;
use App\Support\CabinetRoles;
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

        /*
         * Every cabinet screen is gated on a permission, so an account created
         * without a role can sign in and reach nothing — it looks broken, and
         * the person it was made for has no way to fix it.
         *
         * Owner, because this screen is how an institution is onboarded: the
         * first account handed over is the one that has to be able to add the
         * others. Narrower roles are assigned by the institution itself, on its
         * own staff page.
         */
        $this->assignDefaultRoleForTesting($this->record);
    }

    /** @internal same rule, reachable from a test without a full page render. */
    public function assignDefaultRoleForTesting(MerchantUser $user): void
    {
        if ($user->roles->isEmpty()) {
            $user->assignRole(CabinetRoles::OWNER);
        }
    }
}
