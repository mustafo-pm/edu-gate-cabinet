<?php

namespace App\Filament\Resources\MerchantUsers;

use App\Filament\Resources\Access\CabinetUsers;
use App\Filament\Resources\MerchantUsers\Pages\CreateMerchantUser;
use App\Filament\Resources\MerchantUsers\Pages\EditMerchantUser;
use App\Filament\Resources\MerchantUsers\Pages\ListMerchantUsers;
use App\Models\Merchant;
use App\Models\MerchantUser;
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
class MerchantUserResource extends Resource
{
    protected static ?string $model = MerchantUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Institution users';

    // Filament would otherwise title the page from the model name, which is
    // the database's word for it rather than the one the nav uses.
    protected static ?string $modelLabel = 'institution user';

    protected static ?string $pluralModelLabel = 'institution users';

    protected static ?string $recordTitleAttribute = 'name';

    // Accounts are deactivated, not deleted: their name stays attached to the
    // records they created, and a dangling reference helps nobody.
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CabinetUsers::form($schema, Select::make('merchant_id')
            ->label('Institution')
            ->options(fn () => Merchant::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required());
    }

    public static function table(Table $table): Table
    {
        return CabinetUsers::table($table, TextColumn::make('merchant.name')->label('Institution')->searchable()->limit(24));
    }

    /** Accounts still holding the temporary password we issued. */
    public static function getNavigationBadge(): ?string
    {
        $n = MerchantUser::where('must_change_password', true)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchantUsers::route('/'),
            'create' => CreateMerchantUser::route('/create'),
            'edit' => EditMerchantUser::route('/{record}/edit'),
        ];
    }
}
