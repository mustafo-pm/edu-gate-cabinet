<?php

namespace App\Filament\Resources\BankTransfers\Tables;

use App\Enums\BankTransferStatus;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BankTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')->label('Reference')->fontFamily('mono')->searchable(),
                TextColumn::make('merchant.name')->label('Institution')->searchable()->limit(28),
                TextColumn::make('recipient_mfo')->label('MFO')->fontFamily('mono')->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof BankTransferStatus ? $state->label() : (string) $state)
                    ->color(fn ($state) => $state instanceof BankTransferStatus ? $state->color() : 'gray'),
                TextColumn::make('settlementAccount.label')->label('Sent from')->placeholder('—')->toggleable(),
                TextColumn::make('sent_at')->label('Sent')->dateTime('d M Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BankTransferStatus::cases())
                        ->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
