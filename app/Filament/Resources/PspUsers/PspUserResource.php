<?php

namespace App\Filament\Resources\PspUsers;

use App\Filament\Resources\Access\CabinetUsers;
use App\Filament\Resources\PspUsers\Pages\CreatePspUser;
use App\Filament\Resources\PspUsers\Pages\EditPspUser;
use App\Filament\Resources\PspUsers\Pages\ListPspUsers;
use App\Models\Psp;
use App\Models\PspUser;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Cabinet accounts. The behaviour lives in CabinetUsers — only the tenant
 * column differs between the three types.
 */
class PspUserResource extends Resource
{
    protected static ?string $model = PspUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Partner users';

    // Filament would otherwise title the page from the model name, which is
    // the database's word for it rather than the one the nav uses.
    protected static ?string $modelLabel = 'partner user';

    protected static ?string $pluralModelLabel = 'partner users';

    protected static ?string $recordTitleAttribute = 'name';

    // Accounts are deactivated, not deleted: their name stays attached to the
    // records they created, and a dangling reference helps nobody.
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CabinetUsers::form($schema, Select::make('psp_id')
            ->label('Partner (PSP)')
            ->options(fn () => Psp::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required());
    }

    public static function table(Table $table): Table
    {
        return CabinetUsers::table($table, TextColumn::make('psp.name')->label('Partner (PSP)')->searchable()->limit(24));
    }

    /** Accounts still holding the temporary password we issued. */
    public static function getNavigationBadge(): ?string
    {
        $n = PspUser::where('must_change_password', true)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPspUsers::route('/'),
            'create' => CreatePspUser::route('/create'),
            'edit' => EditPspUser::route('/{record}/edit'),
        ];
    }
}
