<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsTable
{
    /** Filament badge colour for each transaction status. */
    private static function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending' => 'info',
            'cancelled' => 'danger',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('psp.name')
                    ->label('PSP')
                    ->searchable(),
                TextColumn::make('merchant.name')
                    ->searchable(),
                TextColumn::make('partner_transaction_id')
                    ->label('Partner txn')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('net_amount')
                    ->label('Net')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state))
                    ->color(fn ($state) => self::statusColor($state instanceof \BackedEnum ? $state->value : (string) $state)),
                TextColumn::make('paid_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
