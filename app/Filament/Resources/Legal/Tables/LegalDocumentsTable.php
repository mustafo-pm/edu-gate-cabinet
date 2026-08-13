<?php

namespace App\Filament\Resources\Legal\Tables;

use App\Models\LegalDocument;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegalDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('slug')->searchable()->weight('bold'),

                TextColumn::make('type')
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->badge(),

                // What the public actually sees right now, which is not the
                // same as the newest row — a version can be published today to
                // take effect next month.
                TextColumn::make('current')
                    ->label('In force')
                    ->state(function (LegalDocument $record) {
                        $current = $record->currentVersion();

                        return $current === null
                            ? '—'
                            : 'v'.$current->version.($current->effective_from ? ' · '.$current->effective_from->format('d.m.Y') : '');
                    }),

                TextColumn::make('upcoming')
                    ->label('Announced')
                    ->state(function (LegalDocument $record) {
                        $next = $record->upcomingVersion();

                        return $next === null ? '—' : 'v'.$next->version.' · '.$next->effective_from?->format('d.m.Y');
                    })
                    ->color('warning'),

                IconColumn::make('is_active')->label('Public')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
