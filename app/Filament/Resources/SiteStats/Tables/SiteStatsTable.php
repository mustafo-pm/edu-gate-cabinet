<?php

namespace App\Filament\Resources\SiteStats\Tables;

use App\Models\SiteStat;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteStatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label_uz')->label('Label (UZ)')->searchable(),
                TextColumn::make('key')->fontFamily('mono')->toggleable(),
                TextColumn::make('mode')
                    ->badge()
                    ->color(fn (string $state) => $state === 'auto' ? 'info' : 'gray'),
                TextColumn::make('source')
                    ->label('Counts')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->placeholder('—')
                    ->toggleable(),
                // The single most useful column: exactly what the public site prints.
                TextColumn::make('published_value')
                    ->label('On the website')
                    ->state(fn (SiteStat $r) => $r->value())
                    ->badge()
                    ->color(fn (SiteStat $r) => $r->value() === null ? 'warning' : 'success')
                    ->placeholder('hidden — below threshold')
                    ->tooltip(fn (SiteStat $r) => $r->value() === null
                        ? 'The real count has not reached one rounding step yet, so this stays off the site.'
                        : null),
                IconColumn::make('is_published')->label('Enabled')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
