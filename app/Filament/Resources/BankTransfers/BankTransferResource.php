<?php

namespace App\Filament\Resources\BankTransfers;

use App\Enums\BankTransferStatus;
use App\Filament\Resources\BankTransfers\Pages\ListBankTransfers;
use App\Filament\Resources\BankTransfers\Pages\ViewBankTransfer;
use App\Filament\Resources\BankTransfers\Schemas\BankTransferForm;
use App\Filament\Resources\BankTransfers\Schemas\BankTransferInfolist;
use App\Filament\Resources\BankTransfers\Tables\BankTransfersTable;
use App\Models\BankTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BankTransferResource extends Resource
{
    protected static ?string $model = BankTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Postings (provodka)';

    // Filament would otherwise title the page from the model name.
    protected static ?string $modelLabel = 'posting';

    protected static ?string $pluralModelLabel = 'postings (provodka)';

    protected static ?string $recordTitleAttribute = 'reference';

    // Append-only, like every other money table — created by the system only.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** Surface transfers that a human must reconcile. */
    public static function getNavigationBadge(): ?string
    {
        $n = BankTransfer::where('status', BankTransferStatus::Unknown)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return BankTransferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BankTransferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankTransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransfers::route('/'),
            'view' => ViewBankTransfer::route('/{record}'),
        ];
    }
}
