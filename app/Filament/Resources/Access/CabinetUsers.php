<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access;

use App\Support\TempPassword;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared form, table and actions for the three cabinet account types.
 *
 * They differ only in which tenant column they carry, so the parts that decide
 * how a credential is issued and revoked live here once rather than in three
 * near-identical resources that would drift.
 */
class CabinetUsers
{
    /**
     * @param  array<string, mixed>  $tenantField  extra Select for merchant_id / psp_id
     */
    public static function form(Schema $schema, ?Select $tenantField = null): Schema
    {
        return $schema->components(array_filter([
            Section::make('Account')
                ->columns(2)
                ->schema(array_filter([
                    TextInput::make('name')->required(),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Used to sign in. Must be unique across this cabinet.'),
                    TextInput::make('phone')->tel(),
                    $tenantField,
                ])),

            Section::make('Access')
                ->description('A temporary password is generated on create and shown once. '
                    .'There is no password field here on purpose — an admin should never '
                    .'choose, see or store someone else\'s lasting password.')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->default(true)
                        ->helperText('Off blocks sign-in immediately. Accounts are deactivated, never deleted, '
                            .'because their name stays attached to past records.'),
                ]),
        ]));
    }

    public static function table(Table $table, ?TextColumn $tenantColumn = null): Table
    {
        return $table
            ->defaultSort('name')
            ->columns(array_filter([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable()->fontFamily('mono'),
                $tenantColumn,
                TextColumn::make('phone')->placeholder('—')->toggleable(),

                // The state that matters operationally: has the temporary
                // password actually been replaced yet?
                TextColumn::make('password_state')
                    ->label('Password')
                    ->state(fn (Model $r) => $r->must_change_password ? 'Temporary' : 'Set by user')
                    ->badge()
                    ->color(fn (Model $r) => $r->must_change_password ? 'warning' : 'success')
                    ->description(fn (Model $r) => $r->password_changed_at
                        ? 'changed '.$r->password_changed_at->diffForHumans()
                        : 'never changed'),

                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Created')->since()->toggleable(isToggledHiddenByDefault: true),
            ]))
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('must_change_password')->label('Temporary password'),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this password?')
                    ->modalDescription('The current password stops working immediately and any '
                        .'"remember me" session is ended. A new temporary password is shown once — '
                        .'copy it before closing.')
                    ->action(function (Model $record): void {
                        $password = TempPassword::issue($record, by: auth('admin')->user()?->name);

                        self::announce($record, $password, 'Password reset');
                    }),

                EditAction::make(),
            ]);
    }

    /**
     * Create hook: issue the temporary password and show it once.
     *
     * Filament's create form has no password field, so the record is saved with
     * a random unusable one and immediately replaced here — that way a half-
     * finished create can never leave an account with a guessable credential.
     */
    public static function afterCreate(Model $record): void
    {
        $password = TempPassword::issue($record, by: auth('admin')->user()?->name, isNew: true);

        self::announce($record, $password, 'Account created');
    }

    /**
     * The one place the plaintext is ever surfaced. Persistent so it cannot be
     * missed, and never written to a log or a Telegram message.
     */
    private static function announce(Model $record, string $password, string $title): void
    {
        Notification::make()
            ->title($title.' — '.$record->email)
            ->body("Temporary password: {$password}\n\nShown once. It must be changed at first sign-in.")
            ->warning()
            ->persistent()
            ->send();
    }
}
