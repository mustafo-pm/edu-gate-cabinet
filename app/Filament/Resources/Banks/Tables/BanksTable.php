<?php

namespace App\Filament\Resources\Banks\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name_uz')
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->height(28)
                    ->defaultImageUrl(asset('favicon.svg')),
                TextColumn::make('name_uz')->label('Name (UZ)')->searchable()->sortable(),
                TextColumn::make('name_ru')->label('RU')->searchable()->toggleable(),
                TextColumn::make('name_en')->label('EN')->searchable()->toggleable(),
                TextColumn::make('code')->label('Code')->fontFamily('mono')->searchable(),
                TextColumn::make('branches_count')
                    ->label('Branches')
                    ->counts('branches')
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('a2a_supported')->label('A2A')->boolean(),
                TextColumn::make('a2a_driver')->label('Driver')->badge()->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('a2a_supported')->label('A2A supported'),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
