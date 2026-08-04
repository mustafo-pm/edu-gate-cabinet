<?php

namespace App\Filament\Resources\AlertRules\Schemas;

use App\Enums\AlertEvent;
use App\Support\Money;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AlertRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Alert')
                ->schema([
                    Select::make('event')
                        ->label('Event')
                        ->options(collect(AlertEvent::cases())
                            ->mapWithKeys(fn ($c) => [$c->value => $c->emoji().'  '.$c->label()])->all())
                        ->disabled()
                        ->dehydrated()
                        ->helperText(fn ($state) => AlertEvent::tryFrom((string) $state)?->description()),
                    Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->helperText('Turn off to silence this alert without losing its settings.'),

                    Select::make('telegram_chat_id')
                        ->label('Send to')
                        ->relationship('telegramChat', 'chat_id')
                        ->getOptionLabelFromRecordUsing(fn (\App\Models\TelegramChat $r) => $r->label())
                        ->searchable()
                        ->preload()
                        ->placeholder('All active destinations')
                        ->helperText('Pick a specific chat or forum topic, or leave empty to send everywhere.'),
                ]),

            Section::make('Settings')
                ->columns(2)
                ->schema([
                    TextInput::make('threshold')
                        ->label('Threshold (tiyin)')
                        ->numeric()
                        ->helperText(fn ($state) => is_numeric($state)
                            ? 'Low deposit: alert below this. Payment received: only announce at or above this. Currently '.Money::format((int) $state)
                            : '1 UZS = 100 tiyin. Leave empty when the alert has no threshold.')
                        ->visible(fn ($get) => AlertEvent::tryFrom((string) $get('event'))?->usesThreshold() ?? false),

                    Select::make('send_at')
                        ->label('Send at (Tashkent time)')
                        ->options(collect(range(0, 23))
                            ->mapWithKeys(fn ($h) => [sprintf('%02d:00', $h) => sprintf('%02d:00', $h)])->all())
                        ->helperText('The scheduler checks hourly and sends on this hour.')
                        ->visible(fn ($get) => $get('event') === AlertEvent::DailySummary->value),
                ]),
        ]);
    }
}
