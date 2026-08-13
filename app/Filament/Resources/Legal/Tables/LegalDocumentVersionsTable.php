<?php

namespace App\Filament\Resources\Legal\Tables;

use App\Models\LegalDocumentVersion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LegalDocumentVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('document.slug')->label('Document')->searchable()->weight('bold'),
                TextColumn::make('version')->label('v')->sortable(),

                TextColumn::make('status')
                    ->state(fn (LegalDocumentVersion $r) => match (true) {
                        ! $r->isPublished() => 'Draft',
                        $r->isInForce() => 'In force',
                        default => 'Announced',
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'In force' => 'success',
                        'Announced' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('effective_from')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('published_at')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextColumn::make('publisher.name')->label('Published by')->placeholder('—'),
                TextColumn::make('acceptances_count')->counts('acceptances')->label('Accepted'),
                TextColumn::make('change_note')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('legal_document_id')
                    ->label('Document')
                    ->relationship('document', 'slug'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(fn (LegalDocumentVersion $r) => $r->isPublished() ? 'View' : 'Edit'),

                /*
                 * Publishing is one-way and the confirmation says so. After
                 * this the text is frozen: the model refuses further edits,
                 * because an acceptance record is only worth something if what
                 * was accepted cannot change afterwards.
                 */
                Action::make('publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (LegalDocumentVersion $r) => ! $r->isPublished())
                    ->requiresConfirmation()
                    ->modalHeading('Publish this version?')
                    ->modalDescription('The text becomes public and can no longer be edited. Correcting it later means publishing a new version.')
                    ->action(function (LegalDocumentVersion $record) {
                        $record->forceFill([
                            'published_at' => now(),
                            'published_by' => auth('admin')->id(),
                        ])->saveQuietly();

                        Notification::make()
                            ->title('Published')
                            ->body($record->effective_from && $record->effective_from->isFuture()
                                ? 'It takes effect on '.$record->effective_from->format('d.m.Y').'.'
                                : 'It is in force now.')
                            ->success()
                            ->send();
                    }),

                // Only drafts can be removed; the model blocks the rest.
                DeleteAction::make()->visible(fn (LegalDocumentVersion $r) => ! $r->isPublished()),
            ]);
    }
}
