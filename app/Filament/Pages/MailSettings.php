<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Outgoing mail, configurable without a deploy.
 *
 * Mail credentials belong to whoever runs the platform, and a mailbox password
 * gets rotated by hosting providers on their own schedule. Requiring an SSH
 * session and a cache rebuild to follow that is how outgoing mail quietly
 * stays broken for weeks.
 *
 * The password is stored encrypted and never sent back to the browser — the
 * field shows a placeholder and only writes when something new is typed, so
 * saving an unrelated change cannot blank it.
 */
class MailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Mail (SMTP)';

    protected static ?string $title = 'Outgoing mail';

    protected string $view = 'filament.pages.mail-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mail_host' => Settings::get('mail_host'),
            'mail_port' => Settings::get('mail_port', 587),
            'mail_encryption' => Settings::get('mail_encryption', 'tls'),
            'mail_username' => Settings::get('mail_username'),
            // Never the stored secret: it would then sit in the page source.
            'mail_password' => null,
            'mail_from_address' => Settings::get('mail_from_address'),
            'mail_from_name' => Settings::get('mail_from_name', 'EduGate'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SMTP server')
                    ->description('Leave the host empty to fall back to whatever .env says — on this '
                        .'install that is the log driver, which writes mail to a file and sends nothing.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_host')
                            ->label('Host')
                            ->placeholder('mail.edu-gate.uz')
                            ->helperText('Your hosting control panel lists this under mail configuration.'),

                        Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options(['tls' => 'TLS (usually port 587)', 'ssl' => 'SSL (usually port 465)', 'none' => 'None'])
                            ->default('tls')
                            ->native(false)
                            ->live(),

                        TextInput::make('mail_port')
                            ->label('Port')
                            ->numeric()
                            ->default(587)
                            ->helperText(fn (Get $get) => match ($get('mail_encryption')) {
                                'ssl' => 'SSL normally uses 465.',
                                'none' => 'Unencrypted submission normally uses 25 or 587.',
                                default => 'TLS normally uses 587.',
                            }),

                        TextInput::make('mail_username')
                            ->label('Username')
                            ->placeholder('noreply@edu-gate.uz')
                            ->helperText('Usually the full mailbox address.'),

                        TextInput::make('mail_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder(Settings::has('mail_password') ? '•••••••• (unchanged)' : null)
                            ->helperText('Stored encrypted. Leave blank to keep the current one.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Sender')
                    ->description('What recipients see. The address should belong to the domain you are '
                        .'sending from, or providers will treat the mail as forged.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail_from_address')
                            ->label('From address')
                            ->email()
                            ->placeholder('noreply@edu-gate.uz'),

                        TextInput::make('mail_from_name')
                            ->label('From name')
                            ->default('EduGate'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Blank means "leave it alone", not "clear it" — otherwise editing the
        // port would silently wipe the password.
        if (blank($data['mail_password'])) {
            unset($data['mail_password']);
        }

        Settings::put($data);

        Notification::make()
            ->title('Mail settings saved')
            ->body('Send a test message to confirm the server accepts them.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon('heroicon-o-paper-airplane')
                ->schema([
                    TextInput::make('to')
                        ->label('Send to')
                        ->email()
                        ->required()
                        ->default(fn () => auth('admin')->user()?->email),
                ])
                ->action(function (array $data): void {
                    // Save first: testing the form's values rather than the
                    // stored ones would pass on settings that were never kept.
                    $this->save();

                    try {
                        Mail::raw(
                            "This is a test message from the EduGate cabinet.\n\n"
                            .'If you received it, outgoing mail is configured correctly.',
                            fn ($m) => $m->to($data['to'])->subject('EduGate — mail test'),
                        );

                        Notification::make()
                            ->title('Test message sent to '.$data['to'])
                            ->body('Check the inbox, and the spam folder.')
                            ->success()->persistent()->send();
                    } catch (Throwable $e) {
                        // The provider's own words are the only useful part —
                        // "authentication failed" and "connection refused" need
                        // completely different fixes.
                        Notification::make()
                            ->title('The mail server refused it')
                            ->body($e->getMessage())
                            ->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
