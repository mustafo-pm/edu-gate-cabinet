<?php

namespace App\Filament\Resources\AlertRules\Tables;

use App\Enums\AlertEvent;
use App\Models\AlertRule;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AlertRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('event')
                    ->label('Alert')
                    ->formatStateUsing(fn ($state) => $state instanceof AlertEvent
                        ? $state->emoji().'  '.$state->label() : (string) $state)
                    ->description(fn (AlertRule $r) => $r->event instanceof AlertEvent
                        ? $r->event->description() : null)
                    ->wrap(),
                IconColumn::make('is_enabled')->label('On')->boolean(),
                TextColumn::make('threshold')
                    ->label('Threshold')
                    ->formatStateUsing(fn ($state, AlertRule $r) => $r->event instanceof AlertEvent && $r->event->usesThreshold()
                        ? Money::format((int) $state)
                        : '—')
                    ->alignEnd(),
                TextColumn::make('telegramChat.chat_id')
                    ->label('Send to')
                    ->formatStateUsing(fn ($state, AlertRule $r) => $r->telegramChat?->label() ?? 'All destinations')
                    ->badge()
                    ->color(fn (AlertRule $r) => $r->telegramChat ? 'info' : 'gray'),
                TextColumn::make('send_at')->label('Send at')->placeholder('—'),
                TextColumn::make('updated_at')->label('Updated')->since()->toggleable(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
