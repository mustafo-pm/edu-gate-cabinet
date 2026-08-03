<?php

namespace App\Filament\Resources\BankBranches\Tables;

use App\Enums\BranchMatchStatus;
use App\Models\Bank;
use App\Models\BankBranch;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class BankBranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('mfo')
            ->columns([
                TextColumn::make('mfo')
                    ->label('MFO')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bank.name_uz')
                    ->label('Bank')
                    ->placeholder('— not mapped —')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('match_status')
                    ->label('Mapping')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof BranchMatchStatus ? $state->label() : (string) $state)
                    ->color(fn ($state) => $state instanceof BranchMatchStatus ? $state->color() : 'gray'),
                TextColumn::make('name_uz')
                    ->label('Branch')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (BankBranch $record) => $record->name_uz),
            ])
            ->filters([
                SelectFilter::make('match_status')
                    ->label('Mapping status')
                    ->options(collect(BranchMatchStatus::cases())
                        ->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
                SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->relationship('bank', 'name_uz')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Fix a wrong or missing mapping for many branches at once.
                    BulkAction::make('assignBank')
                        ->label('Assign bank')
                        ->icon('heroicon-o-building-library')
                        ->schema([
                            Select::make('bank_id')
                                ->label('Bank')
                                ->options(fn () => Bank::orderBy('name_uz')->pluck('name_uz', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update([
                                'bank_id' => $data['bank_id'],
                                'match_status' => BranchMatchStatus::Confirmed,
                                'match_note' => 'Assigned in admin',
                            ]);
                            Notification::make()
                                ->title($records->count().' branches assigned and confirmed')
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Accepting an auto-match is what makes a branch payable,
                    // so it is a deliberate, confirmed action.
                    BulkAction::make('confirmMapping')
                        ->label('Confirm mapping')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Confirmed branches become eligible to receive A2A transfers. Only confirm mappings you have verified.')
                        ->action(function (Collection $records): void {
                            $skipped = $records->whereNull('bank_id')->count();
                            $ok = $records->whereNotNull('bank_id');
                            $ok->each->update([
                                'match_status' => BranchMatchStatus::Confirmed,
                                'match_note' => 'Confirmed in admin',
                            ]);
                            Notification::make()
                                ->title($ok->count().' branches confirmed'
                                    .($skipped ? " · {$skipped} skipped (no bank assigned)" : ''))
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
