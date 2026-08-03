<?php

namespace App\Filament\Resources\SettlementAccounts\Tables;

use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettlementAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('label')->searchable()->sortable()->weight('bold'),
                TextColumn::make('bank.name_uz')->label('Bank')->searchable()->sortable(),
                TextColumn::make('account')->label('Account')->fontFamily('mono')->searchable(),
                TextColumn::make('mfo')->label('MFO')->fontFamily('mono')->searchable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('balance_updated_at')->label('Updated')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('driver')->badge()->placeholder('—'),
                IconColumn::make('is_default')->label('Default')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
