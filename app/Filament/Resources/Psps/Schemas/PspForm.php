<?php

namespace App\Filament\Resources\Psps\Schemas;

use App\Enums\PspStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PspForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                Select::make('status')
                    ->options(PspStatus::class)
                    ->default('pending')
                    ->required(),
                TextInput::make('commission_bps')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('contact_name'),
                TextInput::make('contact_phone')
                    ->tel(),
                TextInput::make('contact_email')
                    ->email(),
                TextInput::make('webhook_url')
                    ->url(),
            ]);
    }
}
