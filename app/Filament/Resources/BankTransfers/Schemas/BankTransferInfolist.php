<?php

namespace App\Filament\Resources\BankTransfers\Schemas;

use App\Enums\BankTransferStatus;
use App\Models\BankTransfer;
use App\Models\Transaction;
use App\Support\Money;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One provodka, end to end: what left, from which of our accounts, to whom, and
 * — the question accounting actually asks — which tuition payment it settles.
 *
 * The raw request/response payloads are included because when a posting is
 * disputed, the only thing that settles the argument is what we literally sent
 * and what the bank literally answered.
 */
class BankTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Posting')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->label('Our reference')->fontFamily('mono')->copyable(),
                    TextEntry::make('external_id')->label('Bank document id')->fontFamily('mono')
                        ->placeholder('— not returned —')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof BankTransferStatus ? $state->label() : (string) $state)
                        ->color(fn ($state) => $state instanceof BankTransferStatus ? $state->color() : 'gray')
                        ->helperText(fn (BankTransfer $r) => $r->status === BankTransferStatus::Unknown
                            ? 'We never learned whether the money left. Do NOT resend — reconcile against the bank statement.'
                            : null),

                    TextEntry::make('amount')
                        ->label('Amount')
                        ->formatStateUsing(fn ($state) => Money::format((int) $state))
                        ->weight('bold'),
                    TextEntry::make('driver')->badge()->color('gray')->placeholder('—'),
                    TextEntry::make('purpose_text')->label('Purpose')->placeholder('—')->columnSpan(3),
                ]),

            Section::make('Which payment is this for?')
                ->description('The tuition payment(s) this posting settles.')
                ->schema([
                    TextEntry::make('source_kind')
                        ->label('Settles')
                        ->state(fn (BankTransfer $r) => $r->sourceLabel())
                        ->badge()
                        ->color(fn (BankTransfer $r) => $r->transaction_id || $r->payout_id ? 'info' : 'gray')
                        ->helperText(fn (BankTransfer $r) => $r->transaction_id || $r->payout_id
                            ? null
                            : 'No source payment — this is a manual posting or correction.'),

                    RepeatableEntry::make('source_payments')
                        ->label('')
                        ->state(fn (BankTransfer $r) => $r->sourcePayments()->map(fn (Transaction $t) => [
                            'txn' => '#'.$t->id,
                            'student' => $t->student?->fullName() ?? '—',
                            'student_no' => $t->student?->student_id_number ?? '—',
                            'psp' => $t->psp?->name ?? '—',
                            'partner_ref' => $t->partner_transaction_id,
                            'gross' => Money::format((int) $t->amount),
                            'commission' => Money::format((int) $t->commission_amount),
                            'net' => Money::format((int) $t->net_amount),
                            'paid_at' => $t->paid_at?->format('d M Y H:i') ?? '—',
                        ])->all())
                        ->columns(4)
                        ->schema([
                            TextEntry::make('txn')->label('Payment')->fontFamily('mono'),
                            TextEntry::make('student')->label('Student')
                                ->helperText(fn ($state, $record) => $record['student_no'] ?? null),
                            TextEntry::make('psp')->label('Collected by')
                                ->helperText(fn ($state, $record) => $record['partner_ref'] ?? null),
                            TextEntry::make('paid_at')->label('Paid at'),
                            TextEntry::make('gross')->label('Gross'),
                            TextEntry::make('commission')->label('Commission'),
                            TextEntry::make('net')->label('Net to institution')->weight('bold'),
                            TextEntry::make('partner_ref')->label('PSP reference')->fontFamily('mono'),
                        ]),
                ]),

            Section::make('From (our account)')
                ->columns(4)
                ->schema([
                    TextEntry::make('settlementAccount.label')->label('Settlement account')->placeholder('—'),
                    TextEntry::make('settlementAccount.account')->label('Account')->fontFamily('mono')->placeholder('—'),
                    TextEntry::make('settlementAccount.mfo')->label('MFO')->fontFamily('mono')->placeholder('—'),
                    TextEntry::make('settlementAccount.holder_name')->label('Holder')->placeholder('—'),
                ]),

            Section::make('To (recipient at the time of sending)')
                ->description('A snapshot, not live data: an institution may change its bank details later, '
                    .'and an audit has to show where the money actually went.')
                ->columns(4)
                ->schema([
                    TextEntry::make('recipient_name')->label('Name'),
                    TextEntry::make('recipient_account')->label('Account')->fontFamily('mono')->copyable(),
                    TextEntry::make('recipient_mfo')->label('MFO')->fontFamily('mono'),
                    TextEntry::make('recipient_tax')->label('INN / STIR')->fontFamily('mono')->placeholder('—'),
                    TextEntry::make('branch.name_uz')->label('Branch')->placeholder('—')->columnSpan(2),
                    TextEntry::make('branch.bank.name_uz')->label('Bank')->placeholder('—')->columnSpan(2),
                ]),

            Section::make('Timeline')
                ->columns(4)
                ->schema([
                    TextEntry::make('created_at')->label('Created')->dateTime('d M Y H:i:s'),
                    TextEntry::make('sent_at')->label('Sent to bank')->dateTime('d M Y H:i:s')->placeholder('—'),
                    TextEntry::make('confirmed_at')->label('Confirmed')->dateTime('d M Y H:i:s')->placeholder('—'),
                    TextEntry::make('failed_at')->label('Failed')->dateTime('d M Y H:i:s')->placeholder('—'),
                    TextEntry::make('error')->label('Error')->color('danger')->placeholder('—')->columnSpan(4),
                ]),

            Section::make('Raw exchange with the bank')
                ->description('What we sent and what came back — the record that settles a dispute.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    KeyValueEntry::make('request_payload')->label('Request')->placeholder('—'),
                    KeyValueEntry::make('response_payload')->label('Response')->placeholder('—'),
                ]),
        ]);
    }
}
