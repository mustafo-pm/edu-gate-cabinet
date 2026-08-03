<?php

namespace App\Filament\Resources\BankTransfers\Schemas;

use App\Enums\BankTransferStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BankTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('transaction_id')
                    ->relationship('transaction', 'id'),
                Select::make('merchant_id')
                    ->relationship('merchant', 'name')
                    ->required(),
                Select::make('settlement_account_id')
                    ->relationship('settlementAccount', 'id'),
                TextInput::make('bank_branch_id')
                    ->numeric(),
                TextInput::make('reference')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('recipient_account')
                    ->required(),
                TextInput::make('recipient_mfo')
                    ->required(),
                TextInput::make('recipient_tax'),
                TextInput::make('recipient_name')
                    ->required(),
                TextInput::make('purpose_code'),
                TextInput::make('purpose_text'),
                TextInput::make('driver'),
                Select::make('status')
                    ->options(BankTransferStatus::class)
                    ->default('pending')
                    ->required(),
                TextInput::make('external_id'),
                Textarea::make('request_payload')
                    ->columnSpanFull(),
                Textarea::make('response_payload')
                    ->columnSpanFull(),
                Textarea::make('error')
                    ->columnSpanFull(),
                DateTimePicker::make('sent_at'),
                DateTimePicker::make('confirmed_at'),
                DateTimePicker::make('failed_at'),
            ]);
    }
}
