<?php

namespace App\Filament\Resources\Merchants\Tables;

use App\Filament\Resources\MerchantBankAccounts\MerchantBankAccountResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MerchantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('stir')
                    ->searchable(),
                TextColumn::make('mfo')
                    ->searchable(),
                TextColumn::make('bank_account')
                    ->searchable(),
                TextColumn::make('bank_name')
                    ->searchable(),
                TextColumn::make('commission_bps')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->searchable(),
                TextColumn::make('contact_phone')
                    ->searchable(),
                TextColumn::make('contact_email')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),

                // Where this institution is paid. Its own screen rather than a
                // field on the form: several accounts, and an approval step.
                Action::make('accounts')
                    ->label('Accounts')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn ($record) => MerchantBankAccountResource::getUrl('index', [
                        'tableFilters[merchant_id][value]' => $record->id,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
