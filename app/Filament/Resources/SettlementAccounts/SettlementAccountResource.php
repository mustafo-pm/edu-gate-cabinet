<?php

namespace App\Filament\Resources\SettlementAccounts;

use App\Filament\Resources\SettlementAccounts\Pages\CreateSettlementAccount;
use App\Filament\Resources\SettlementAccounts\Pages\EditSettlementAccount;
use App\Filament\Resources\SettlementAccounts\Pages\ListSettlementAccounts;
use App\Filament\Resources\SettlementAccounts\Schemas\SettlementAccountForm;
use App\Filament\Resources\SettlementAccounts\Tables\SettlementAccountsTable;
use App\Models\SettlementAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SettlementAccountResource extends Resource
{
    protected static ?string $model = SettlementAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Our accounts';

    public static function form(Schema $schema): Schema
    {
        return SettlementAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettlementAccountsTable::configure($table);
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
            'index' => ListSettlementAccounts::route('/'),
            'create' => CreateSettlementAccount::route('/create'),
            'edit' => EditSettlementAccount::route('/{record}/edit'),
        ];
    }
}
