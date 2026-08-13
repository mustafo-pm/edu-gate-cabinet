<?php

namespace App\Filament\Resources\Legal;

use App\Filament\Resources\Legal\Pages\CreateLegalDocumentVersion;
use App\Filament\Resources\Legal\Pages\EditLegalDocumentVersion;
use App\Filament\Resources\Legal\Pages\ListLegalDocumentVersions;
use App\Filament\Resources\Legal\Schemas\LegalDocumentVersionForm;
use App\Filament\Resources\Legal\Tables\LegalDocumentVersionsTable;
use App\Models\LegalDocumentVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Where the text is written, reviewed and published.
 *
 * A draft is freely editable. Publishing freezes it: the model refuses to
 * update or delete a published row, because an acceptance record is only worth
 * something if the text it points at cannot change afterwards. Fixing a typo in
 * a published document means writing version 4.
 */
class LegalDocumentVersionResource extends Resource
{
    protected static ?string $model = LegalDocumentVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Legal document text';

    protected static ?string $modelLabel = 'version';

    public static function form(Schema $schema): Schema
    {
        return LegalDocumentVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalDocumentVersionsTable::configure($table);
    }

    /** Drafts waiting to be published or reviewed. */
    public static function getNavigationBadge(): ?string
    {
        $drafts = LegalDocumentVersion::whereNull('published_at')->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalDocumentVersions::route('/'),
            'create' => CreateLegalDocumentVersion::route('/create'),
            'edit' => EditLegalDocumentVersion::route('/{record}/edit'),
        ];
    }
}
