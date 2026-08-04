<?php

namespace App\Filament\Resources\SiteStats\Schemas;

use App\Enums\StatSource;
use App\Models\SiteStat;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SiteStatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Label')
                ->description('The caption under the figure. The website offers uz / ru / en.')
                ->columns(3)
                ->schema([
                    TextInput::make('label_uz')->label('Label (Uzbek)')->required(),
                    TextInput::make('label_ru')->label('Label (Russian)'),
                    TextInput::make('label_en')->label('Label (English)'),
                    TextInput::make('key')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Stable key, e.g. institutions.'),
                ]),

            Section::make('Value')
                ->description('Figures are counts or claims only — turnover and balances are never '
                    .'published, so there is no money source to pick.')
                ->columns(2)
                ->schema([
                    Radio::make('mode')
                        ->options([
                            'manual' => 'Manual — a fixed string I type',
                            'auto' => 'Automatic — a live count, rounded down',
                        ])
                        ->default('manual')
                        ->required()
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('manual_value')
                        ->label('Displayed value')
                        ->maxLength(40)
                        ->placeholder('0–30s')
                        ->required(fn (Get $get) => $get('mode') !== 'auto')
                        ->visible(fn (Get $get) => $get('mode') !== 'auto')
                        ->helperText('Shown exactly as typed.'),

                    Select::make('source')
                        ->label('Count')
                        ->options(StatSource::options())
                        ->native(false)
                        ->required(fn (Get $get) => $get('mode') === 'auto')
                        ->visible(fn (Get $get) => $get('mode') === 'auto'),

                    TextInput::make('round_to')
                        ->label('Round down to')
                        ->numeric()
                        ->minValue(1)
                        ->default(10)
                        ->visible(fn (Get $get) => $get('mode') === 'auto')
                        ->helperText(fn (Get $get) => self::preview($get))
                        ->live(onBlur: true),
                ]),

            Section::make('Publishing')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')->label('Shown on edu-gate.uz')->default(true),
                    TextInput::make('sort_order')->numeric()->default(0),
                ]),
        ]);
    }

    /** Shows the admin what the site will actually print, before saving. */
    private static function preview(Get $get): string
    {
        $source = StatSource::tryFrom((string) $get('source'));

        if (! $source) {
            return 'Pick a count to preview the published value.';
        }

        $rounded = SiteStat::roundDown($source->count(), (int) ($get('round_to') ?: 1));

        return $rounded === null
            ? 'The real count is still below one step, so this figure stays hidden.'
            : 'Will publish as “'.$rounded.'+”.';
    }
}
