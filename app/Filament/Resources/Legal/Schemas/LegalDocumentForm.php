<?php

namespace App\Filament\Resources\Legal\Schemas;

use App\Enums\LegalDocumentType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LegalDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')
                ->description('The text is added afterwards, as a version. Creating a document publishes nothing.')
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->rule('regex:/^[a-z0-9-]{2,60}$/')
                        ->helperText('Appears in the public address: /hujjat/oferta. Changing it breaks every link already printed or sent.'),

                    Select::make('type')
                        ->options(LegalDocumentType::options())
                        ->required()
                        ->helperText('Decides whose acceptance we record.'),

                    Toggle::make('is_active')
                        ->label('Served publicly')
                        ->helperText('Off hides the page and the API entry, whatever versions exist.'),

                    TextInput::make('sort_order')->numeric()->default(0),
                ]),
        ]);
    }
}
