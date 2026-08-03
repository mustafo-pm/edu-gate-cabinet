<?php

namespace App\Filament\Resources\Banks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('name_uz')->label('Name (Uzbek)')->required(),
                    TextInput::make('name_ru')->label('Name (Russian)'),
                    TextInput::make('name_en')->label('Name (English)'),
                    TextInput::make('code')->label('Registry code')->required()
                        ->helperText('Bank code from the registry, e.g. 20012.'),
                    TextInput::make('slug')->required()
                        ->helperText('Stable key used by integrations, e.g. aloqabank.'),
                    TextInput::make('swift')->label('SWIFT / BIC'),
                ]),

            Section::make('Logo')
                ->schema([
                    FileUpload::make('logo_path')
                        ->image()
                        ->disk('public')
                        ->directory('bank-logos')
                        ->imagePreviewHeight('60')
                        ->helperText('SVG preferred, or PNG at 256px+. Shown in the cabinets.'),
                ]),

            Section::make('A2A transfers')
                ->columns(2)
                ->schema([
                    Toggle::make('a2a_supported')
                        ->label('A2A supported')
                        ->helperText('Enable only once we hold an account and a working integration here.'),
                    TextInput::make('a2a_driver')
                        ->label('Driver key')
                        ->helperText('Integration class key, e.g. universalbank.'),
                ]),

            Section::make('Display')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')->default(true),
                    TextInput::make('sort_order')->numeric()->default(0),
                ]),
        ]);
    }
}
