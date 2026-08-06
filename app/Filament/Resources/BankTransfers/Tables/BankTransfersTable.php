<?php

namespace App\Filament\Resources\BankTransfers\Tables;

use App\Actions\Payments\SettleTransaction;
use App\Enums\BankTransferStatus;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Merchant;
use App\Services\A2a\A2aDriverManager;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The accounting register of money leaving EduGate — every provodka sent to a
 * bank, and which tuition payment it settles.
 *
 * Built for reconciliation rather than browsing: the columns are the ones you
 * want open next to a bank statement (our reference, the bank's own id, the
 * account and MFO the money went to, the amount, when it was sent), and the
 * source payment is always visible so a posting can be traced to the student
 * whose fee it carries.
 */
class BankTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable()
                    ->description(fn (BankTransfer $r) => $r->external_id ? 'bank: '.$r->external_id : null),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('merchant.name')
                    ->label('Institution')
                    ->searchable()
                    ->limit(24)
                    ->tooltip(fn (BankTransfer $r) => $r->merchant?->name),

                // The whole point of this screen: what is this provodka FOR?
                TextColumn::make('source')
                    ->label('For payment')
                    ->state(fn (BankTransfer $r) => $r->sourceLabel())
                    ->description(fn (BankTransfer $r) => $r->transaction?->student?->fullName())
                    ->badge()
                    ->color(fn (BankTransfer $r) => $r->transaction_id || $r->payout_id ? 'info' : 'gray'),

                TextColumn::make('recipient_account')
                    ->label('To account')
                    ->fontFamily('mono')
                    ->searchable()
                    ->description(fn (BankTransfer $r) => 'MFO '.$r->recipient_mfo
                        .($r->branch?->bank?->name_uz ? ' · '.$r->branch->bank->name_uz : ''))
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => Money::format((int) $state))
                    ->alignEnd()
                    ->sortable()
                    // Sums the FILTERED set, so "everything confirmed in March"
                    // is a single glance rather than an export.
                    ->summarize(Sum::make()->formatStateUsing(fn ($state) => Money::format((int) $state))),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof BankTransferStatus ? $state->label() : (string) $state)
                    ->color(fn ($state) => $state instanceof BankTransferStatus ? $state->color() : 'gray')
                    ->description(fn (BankTransfer $r) => $r->status === BankTransferStatus::Unknown
                        ? 'reconcile by hand' : null),

                TextColumn::make('driver')
                    ->label('Driver')
                    ->badge()->color('gray')->placeholder('—')
                    ->toggleable(),

                TextColumn::make('settlementAccount.label')
                    ->label('Sent from')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BankTransferStatus::cases())
                        ->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                    ->multiple(),

                SelectFilter::make('driver')
                    ->options(fn () => BankTransfer::query()
                        ->whereNotNull('driver')->distinct()->pluck('driver', 'driver')->all()),

                SelectFilter::make('merchant_id')
                    ->label('Institution')
                    ->options(fn () => Merchant::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                // Reconciliation happens one bank statement at a time, so
                // filtering by the receiving bank is the primary workflow.
                SelectFilter::make('bank')
                    ->label('Receiving bank')
                    ->options(fn () => Bank::orderBy('name_uz')->pluck('name_uz', 'id')->all())
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('branch', fn (Builder $b) => $b->where('bank_id', $data['value']))
                        : $q),

                Filter::make('created_between')
                    ->schema([
                        DatePicker::make('from')->label('Created from'),
                        DatePicker::make('until')->label('Created until'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['until'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))),

                Filter::make('needs_review')
                    ->label('Needs reconciliation')
                    ->query(fn (Builder $q) => $q->where('status', BankTransferStatus::Unknown))
                    ->toggle(),
            ])
            ->recordActions([
                // Blocked or rejected postings can be sent once the cause is
                // fixed. `unknown` deliberately has no send action — we do not
                // know whether that money left, and a button that resends it is
                // how an institution gets paid twice.
                Action::make('send')
                    ->label(fn (BankTransfer $r) => $r->status === BankTransferStatus::Failed
                        ? 'Re-send as new posting' : 'Send to bank')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (BankTransfer $r) => in_array(
                        $r->status, [BankTransferStatus::Pending, BankTransferStatus::Failed], true,
                    ))
                    ->requiresConfirmation()
                    ->modalDescription(fn (BankTransfer $r) => $r->status === BankTransferStatus::Failed
                        ? 'The bank already rejected this order id, so a NEW posting is appended and sent. '
                          .'The original stays for the audit trail.'
                        : 'This posting has never reached the bank. Routing is re-resolved before sending.')
                    ->action(function (BankTransfer $record, SettleTransaction $action): void {
                        $result = $action->retry($record);

                        $ok = $result->status !== BankTransferStatus::Pending;

                        Notification::make()
                            ->title($ok ? "Sent — {$result->reference} is {$result->status->value}" : 'Still blocked')
                            ->body($result->error)
                            ->{$ok ? 'success' : 'warning'}()
                            ->send();
                    }),

                // Safe for every non-terminal status, including `unknown`:
                // asking the bank what happened never moves money.
                Action::make('recheck')
                    ->label('Re-check status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (BankTransfer $r) => in_array(
                        $r->status, [BankTransferStatus::Sent, BankTransferStatus::Unknown], true,
                    ))
                    ->action(function (BankTransfer $record, A2aDriverManager $drivers): void {
                        $driver = $drivers->for($record->driver);

                        if (! $driver) {
                            Notification::make()->title("No driver [{$record->driver}]")->danger()->send();

                            return;
                        }

                        $result = $driver->status($record);

                        if ($result->isFinal()) {
                            $record->forceFill([
                                'status' => $result->status,
                                'external_id' => $result->externalId ?? $record->external_id,
                                'response_payload' => $result->raw,
                                'error' => $result->message,
                                'confirmed_at' => $result->status === BankTransferStatus::Confirmed ? now() : null,
                                'failed_at' => $result->status === BankTransferStatus::Failed ? now() : null,
                            ])->save();
                        }

                        Notification::make()
                            ->title('Bank says: '.$result->status->label())
                            ->body($result->message)
                            ->{$result->isFinal() ? 'success' : 'info'}()
                            ->send();
                    }),

                ViewAction::make(),
            ]);
    }
}
