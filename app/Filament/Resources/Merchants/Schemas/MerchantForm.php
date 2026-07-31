<?php

namespace App\Filament\Resources\Merchants\Schemas;

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MerchantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options(MerchantType::class)
                    ->required(),
                Select::make('status')
                    ->options(MerchantStatus::class)
                    ->default('pending')
                    ->required(),
                TextInput::make('stir'),
                TextInput::make('mfo'),
                TextInput::make('bank_account'),
                TextInput::make('bank_name'),
                TextInput::make('commission_bps')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('contact_name'),
                TextInput::make('contact_phone')
                    ->tel(),
                TextInput::make('contact_email')
                    ->email(),
            ]);
    }
}
