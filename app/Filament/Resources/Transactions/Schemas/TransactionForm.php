<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('psp_id')
                    ->relationship('psp', 'name')
                    ->required(),
                Select::make('merchant_id')
                    ->relationship('merchant', 'name')
                    ->required(),
                Select::make('student_id')
                    ->relationship('student', 'id'),
                TextInput::make('payment_schedule_id')
                    ->numeric(),
                TextInput::make('partner_transaction_id')
                    ->required(),
                TextInput::make('check_id'),
                TextInput::make('idempotency_key'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('commission_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('net_amount')
                    ->required()
                    ->numeric(),
                Select::make('status')
                    ->options(TransactionStatus::class)
                    ->default('pending')
                    ->required(),
                TextInput::make('gateway'),
                TextInput::make('refunded_transaction_id')
                    ->numeric(),
                Textarea::make('meta')
                    ->columnSpanFull(),
                DateTimePicker::make('paid_at'),
            ]);
    }
}
