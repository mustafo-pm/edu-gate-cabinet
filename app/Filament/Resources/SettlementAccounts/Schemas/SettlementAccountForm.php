<?php

namespace App\Filament\Resources\SettlementAccounts\Schemas;

use App\Support\Money;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SettlementAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->description("One of EduGate's own bank accounts — the sender side of an A2A transfer. Holding an account at the recipient's own bank makes the transfer same-bank instead of interbank.")
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->helperText('Internal name, e.g. "Aloqabank — main".'),
                    Select::make('bank_id')
                        ->label('Bank')
                        ->relationship('bank', 'name_uz')
                        ->searchable()
                        ->preload()
                        ->required(),
                ]),

            Section::make('Payment details')
                ->description('These map onto the A2A payload sender block.')
                ->columns(2)
                ->schema([
                    TextInput::make('account')->label('Account number')->required()
                        ->helperText('sender.account'),
                    TextInput::make('mfo')->label('MFO')->required()->maxLength(5)
                        ->helperText('sender.code_filial'),
                    TextInput::make('tax')->label('Tax ID (STIR)')->required()
                        ->helperText('sender.tax'),
                    TextInput::make('holder_name')->label('Account holder (legal name)')->required()
                        ->helperText('sender.name'),
                ]),

            Section::make('Deposit')
                ->columns(2)
                ->schema([
                    TextInput::make('balance')
                        ->label('Balance (tiyin)')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->helperText(fn ($state) => 'Stored in tiyin (1 UZS = 100 tiyin)'
                            .(is_numeric($state) ? ' — currently '.Money::format((int) $state) : '')
                            .'. Last known figure, mirrors the bank.'),
                    DateTimePicker::make('balance_updated_at')->label('Balance updated at'),
                ]),

            Section::make('Routing')
                ->columns(2)
                ->schema([
                    TextInput::make('driver')->label('A2A driver key')
                        ->helperText('e.g. universalbank'),
                    Toggle::make('is_default')
                        ->label('Default rail')
                        ->helperText('Used when we hold no account at the recipient bank.'),
                    Toggle::make('is_active')->default(true),
                ]),
        ]);
    }
}
