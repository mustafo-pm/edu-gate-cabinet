<?php

namespace App\Filament\Resources\Legal;

use App\Filament\Resources\Legal\Pages\CreateLegalDocument;
use App\Filament\Resources\Legal\Pages\EditLegalDocument;
use App\Filament\Resources\Legal\Pages\ListLegalDocuments;
use App\Filament\Resources\Legal\Schemas\LegalDocumentForm;
use App\Filament\Resources\Legal\Tables\LegalDocumentsTable;
use App\Models\LegalDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The documents themselves — slug, type, on/off. The text lives in versions,
 * which have their own resource, because the text is the part that has to be
 * provable after the fact and therefore cannot be edited in place.
 */
class LegalDocumentResource extends Resource
{
    protected static ?string $model = LegalDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Legal documents';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return LegalDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalDocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalDocuments::route('/'),
            'create' => CreateLegalDocument::route('/create'),
            'edit' => EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
