<?php

namespace App\Filament\Resources\AdminUsers;

use App\Filament\Resources\Access\CabinetUsers;
use App\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Models\AdminUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Cabinet accounts. The behaviour lives in CabinetUsers — only the tenant
 * column differs between the three types.
 */
class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'EduGate admins';

    // Filament would otherwise title the page from the model name, which is
    // the database's word for it rather than the one the nav uses.
    protected static ?string $modelLabel = 'EduGate admin';

    protected static ?string $pluralModelLabel = 'EduGate admins';

    protected static ?string $recordTitleAttribute = 'name';

    // Accounts are deactivated, not deleted: their name stays attached to the
    // records they created, and a dangling reference helps nobody.
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CabinetUsers::form($schema, null);
    }

    public static function table(Table $table): Table
    {
        return CabinetUsers::table($table, null);
    }

    /** Accounts still holding the temporary password we issued. */
    public static function getNavigationBadge(): ?string
    {
        $n = AdminUser::where('must_change_password', true)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
