<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Enums\PartnerCategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->description('The name shown under the logo. The website offers uz / ru / en.')
                ->columns(2)
                ->schema([
                    TextInput::make('name_uz')
                        ->label('Name (Uzbek)')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('name_ru')->label('Name (Russian)'),
                    TextInput::make('name_en')->label('Name (English)'),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Stable key, e.g. aloqabank.'),
                    Select::make('category')
                        ->options(PartnerCategory::options())
                        ->required()
                        ->native(false)
                        ->helperText('Decides which row of the logo wall it appears in.'),
                    TextInput::make('website_url')
                        ->label('Website')
                        ->url()
                        ->prefixIcon('heroicon-o-link')
                        ->helperText('Optional — the logo links here.'),
                ]),

            Section::make('Logo')
                ->schema([
                    FileUpload::make('logo_path')
                        ->image()
                        ->disk('public')
                        ->directory('partner-logos')
                        ->imagePreviewHeight('60')
                        ->helperText('SVG preferred, or PNG at 256px+ on a transparent background. '
                            .'The wall renders logos at a uniform height, so wide marks look best.'),
                ]),

            Section::make('Publishing')
                ->description('Naming an organisation publicly is a consent decision — publish only '
                    .'once they have agreed to be listed as a partner.')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Published on edu-gate.uz')
                        ->helperText('Off by default. Nothing reaches the public site until this is on.'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower shows first within its row.'),
                ]),
        ]);
    }
}
