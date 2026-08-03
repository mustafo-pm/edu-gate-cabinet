<?php

namespace App\Filament\Resources\BankBranches\Schemas;

use App\Enums\BranchMatchStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankBranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Routing')
                ->description('The MFO identifies the branch holding an account. Money is only routed to branches whose mapping is Confirmed.')
                ->columns(2)
                ->schema([
                    TextInput::make('mfo')
                        ->label('MFO')
                        ->required()
                        ->maxLength(5)
                        ->helperText('5 digits, unique.'),
                    Select::make('bank_id')
                        ->label('Bank')
                        ->relationship('bank', 'name_uz')
                        ->searchable()
                        ->preload(),
                    Select::make('match_status')
                        ->label('Mapping status')
                        ->options(collect(BranchMatchStatus::cases())
                            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                        ->default(BranchMatchStatus::Unmapped->value)
                        ->required()
                        ->helperText('Only "Confirmed" branches can receive transfers.'),
                    TextInput::make('match_note')->label('Note'),
                ]),

            Section::make('Branch details')
                ->columns(2)
                ->schema([
                    TextInput::make('name_uz')->label('Name (UZ)')->columnSpanFull(),
                    TextInput::make('name_ru')->label('Name (RU)'),
                    TextInput::make('name_en')->label('Name (EN)'),
                    TextInput::make('region'),
                    Toggle::make('is_active')->default(true),
                ]),
        ]);
    }
}
