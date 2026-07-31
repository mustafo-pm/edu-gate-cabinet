<?php

namespace App\Filament\Resources\Psps;

use App\Filament\Resources\Psps\Pages\CreatePsp;
use App\Filament\Resources\Psps\Pages\EditPsp;
use App\Filament\Resources\Psps\Pages\ListPsps;
use App\Filament\Resources\Psps\Schemas\PspForm;
use App\Filament\Resources\Psps\Tables\PspsTable;
use App\Models\Psp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PspResource extends Resource
{
    protected static ?string $model = Psp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PspForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PspsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPsps::route('/'),
            'create' => CreatePsp::route('/create'),
            'edit' => EditPsp::route('/{record}/edit'),
        ];
    }
}
