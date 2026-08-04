<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Enums\PartnerCategory;
use App\Models\Partner;
use App\Support\PartnerImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->height(28)
                    ->placeholder('— none —'),
                TextColumn::make('name_uz')->label('Name (UZ)')->searchable()->sortable(),
                TextColumn::make('name_ru')->label('RU')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('name_en')->label('EN')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (PartnerCategory $state) => $state->label())
                    ->color(fn (PartnerCategory $state) => $state->color()),
                TextColumn::make('source_type')
                    ->label('Imported from')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : null)
                    ->badge()->color('gray')->placeholder('added by hand')
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean()
                    ->tooltip(fn (Partner $r) => $r->is_published
                        ? 'Visible on edu-gate.uz'
                        : 'Staged — not on the public site'),
            ])
            ->filters([
                SelectFilter::make('category')->options(PartnerCategory::options()),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->headerActions([
                // Convenience only: it pre-fills a marketing row from an
                // operational record. It never publishes — consent to be named
                // publicly is a separate decision from being a customer.
                Action::make('import')
                    ->label('Import from registry')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->modalDescription('Creates unpublished partner rows you can then review, '
                        .'add a logo to, and publish.')
                    ->schema([
                        Select::make('type')
                            ->label('Source')
                            ->options(PartnerImporter::sources())
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('records')
                            ->label('Records')
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->options(fn (Get $get) => PartnerImporter::options($get('type')))
                            ->helperText('Already-imported records are not listed again.'),
                    ])
                    ->action(function (array $data): void {
                        $created = PartnerImporter::import($data['type'], $data['records']);

                        Notification::make()
                            ->title($created.' partner row(s) created')
                            ->body('They are unpublished. Add logos, then switch "Published" on.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkAction::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-globe-alt')
                    ->requiresConfirmation()
                    ->modalDescription('These logos will appear on edu-gate.uz. Confirm each '
                        .'organisation has agreed to be named publicly.')
                    ->action(fn (Collection $records) => $records->each->update(['is_published' => true]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->action(fn (Collection $records) => $records->each->update(['is_published' => false]))
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ]);
    }
}
