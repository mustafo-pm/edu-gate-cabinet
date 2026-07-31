<?php

namespace App\Filament\Resources\Payouts\Schemas;

use App\Enums\PayoutStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('merchant_id')
                    ->relationship('merchant', 'name')
                    ->required(),
                TextInput::make('reference')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Select::make('status')
                    ->options(PayoutStatus::class)
                    ->default('pending')
                    ->required(),
                TextInput::make('bank_account'),
                TextInput::make('bank_name'),
                DateTimePicker::make('processed_at'),
                TextInput::make('failure_reason'),
            ]);
    }
}
