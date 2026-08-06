<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\BankTransferStatus;
use App\Models\Bank;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which rails we can actually send money on.
 *
 * "A2A supported" on a bank is only an intention — a rail is usable when three
 * things line up: the flag is on, a driver is configured, and we hold an active
 * account there to send from. This page shows all three together and names what
 * is missing, so nobody discovers a half-configured bank at settlement time.
 */
class BankDrivers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Bank drivers';

    protected static ?string $title = 'Bank drivers';

    protected string $view = 'filament.pages.bank-drivers';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->defaultSort('name_uz')
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('public')
                    ->height(24),

                TextColumn::make('name_uz')
                    ->label('Bank')
                    ->searchable()
                    ->description(fn (Bank $r) => 'code '.$r->code),

                IconColumn::make('a2a_supported')->label('A2A')->boolean(),

                TextColumn::make('a2a_driver')
                    ->label('Driver')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'info' : 'gray')
                    ->placeholder('none'),

                TextColumn::make('accounts_count')
                    ->label('Our accounts')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('accounts_balance')
                    ->label('Balance (last known)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => Money::format((int) ($state ?? 0)))
                    ->description('mirrors the bank, not authoritative'),

                TextColumn::make('postings_count')
                    ->label('Postings')
                    ->alignEnd()
                    ->badge()->color('gray'),

                TextColumn::make('postings_unknown')
                    ->label('Unreconciled')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),

                // The column that actually answers "can we use this bank?".
                TextColumn::make('readiness')
                    ->label('Status')
                    ->state(fn (Bank $r) => self::readiness($r))
                    ->badge()
                    ->color(fn (Bank $r) => self::readiness($r) === 'Ready' ? 'success' : 'warning'),
            ])
            ->recordUrl(null)
            ->paginated([25, 50, 'all']);
    }

    /**
     * Only banks that are meant to be rails. The 38-row registry is reference
     * data — listing every one of them here would bury the two that matter.
     */
    private function baseQuery(): Builder
    {
        $transfers = 'select count(*) from bank_transfers bt
                      join settlement_accounts sa on sa.id = bt.settlement_account_id
                      where sa.bank_id = banks.id';

        return Bank::query()
            ->where(fn (Builder $q) => $q
                ->where('a2a_supported', true)
                ->orWhereNotNull('a2a_driver')
                ->orWhereHas('settlementAccounts'))
            ->withCount(['settlementAccounts as accounts_count' => fn (Builder $q) => $q->where('is_active', true)])
            ->addSelect(['*'])
            ->selectSub(
                'select coalesce(sum(balance), 0) from settlement_accounts
                 where settlement_accounts.bank_id = banks.id and is_active = 1',
                'accounts_balance',
            )
            ->selectSub($transfers, 'postings_count')
            ->selectSub(
                $transfers." and bt.status = '".BankTransferStatus::Unknown->value."'",
                'postings_unknown',
            );
    }

    /** Names the missing piece rather than just saying "not ready". */
    private static function readiness(Bank $bank): string
    {
        if (! $bank->a2a_supported) {
            return 'A2A off';
        }

        if (blank($bank->a2a_driver)) {
            return 'No driver';
        }

        if ((int) ($bank->accounts_count ?? 0) === 0) {
            return 'No account';
        }

        return 'Ready';
    }
}
