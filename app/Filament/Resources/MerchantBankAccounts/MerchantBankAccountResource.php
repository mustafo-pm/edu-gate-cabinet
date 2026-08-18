<?php

namespace App\Filament\Resources\MerchantBankAccounts;

use App\Enums\MerchantBankAccountStatus;
use App\Filament\Resources\MerchantBankAccounts\Pages\ListMerchantBankAccounts;
use App\Models\MerchantBankAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approving where an institution's money is sent.
 *
 * The whole reason this screen exists: an institution proposes an account in
 * its own cabinet, and nothing is paid into it until somebody here has checked
 * the number against the institution's documents. Without that step a single
 * stolen cabinet password redirects a term's tuition.
 */
class MerchantBankAccountResource extends Resource
{
    protected static ?string $model = MerchantBankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Banking';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Institution accounts';

    protected static ?string $modelLabel = 'institution account';

    /** Accounts waiting on us. Money is held until these are dealt with. */
    public static function getNavigationBadge(): ?string
    {
        $pending = MerchantBankAccount::withoutGlobalScopes()
            ->where('status', MerchantBankAccountStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        // Admins see every institution; the tenant scope is for cabinets.
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('bank_name')->disabled(),
            TextInput::make('mfo')->disabled(),
            TextInput::make('account_number')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('merchant.name')->label('Institution')->searchable()->weight('bold'),
                TextColumn::make('bank_name')->searchable(),
                TextColumn::make('mfo')->fontFamily('mono'),
                // Shown in full here, unlike the cabinet: checking it against a
                // document is the entire job on this screen.
                TextColumn::make('account_number')->fontFamily('mono')->searchable()->copyable(),
                TextColumn::make('label')->placeholder('—')->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => match ($state->value) {
                        'active' => 'success', 'pending' => 'warning',
                        'rejected' => 'danger', default => 'gray',
                    }),

                IconColumn::make('is_primary')->label('Settles here')->boolean(),
                TextColumn::make('approver.name')->label('Approved by')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->dateTime('d.m.Y H:i')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(MerchantBankAccountStatus::options()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (MerchantBankAccount $r) => $r->status === MerchantBankAccountStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Approve this account?')
                    ->modalDescription('Settlements can be sent here once approved. Check the number against the institution\'s documents first.')
                    ->action(function (MerchantBankAccount $record) {
                        $record->forceFill([
                            'status' => MerchantBankAccountStatus::Active,
                            'approved_at' => now(),
                            'approved_by' => auth('admin')->id(),
                            'rejection_reason' => null,
                        ])->save();

                        Notification::make()->title('Approved')->success()->send();
                    }),

                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MerchantBankAccount $r) => $r->status === MerchantBankAccountStatus::Pending)
                    ->schema([
                        TextInput::make('reason')
                            ->label('Reason')
                            ->required()
                            // Shown verbatim in the institution's cabinet, so
                            // "no" without a reason wastes everybody's week.
                            ->helperText('The institution sees this.'),
                    ])
                    ->action(function (MerchantBankAccount $record, array $data) {
                        $record->forceFill([
                            'status' => MerchantBankAccountStatus::Rejected,
                            'rejection_reason' => $data['reason'],
                        ])->save();

                        Notification::make()->title('Rejected')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMerchantBankAccounts::route('/')];
    }
}
