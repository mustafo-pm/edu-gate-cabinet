<?php

declare(strict_types=1);

use App\Filament\Pages\MailSettings;
use App\Models\AdminUser;
use App\Models\Setting;
use App\Providers\RuntimeConfigProvider;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

/**
 * SMTP credentials edited from the admin panel rather than .env.
 *
 * Two things must hold: the password is never readable from the database or
 * the page, and consulting settings must survive the table not existing —
 * this is read while the framework boots, including during `migrate` on a
 * fresh install.
 */
beforeEach(function () {
    Settings::flush();
});

it('encrypts the SMTP password at rest', function () {
    Settings::put(['mail_password' => 'the-real-password']);

    $row = Setting::where('key', 'mail_password')->first();

    expect($row->value)->not->toBe('the-real-password')
        ->and($row->is_encrypted)->toBeTrue()
        // A database dump travels; a plaintext credential travels with it.
        ->and(Settings::get('mail_password'))->toBe('the-real-password');
});

it('stores non-secret settings in the clear so they can be read at a glance', function () {
    Settings::put(['mail_host' => 'mail.edu-gate.uz']);

    expect(Setting::where('key', 'mail_host')->first()->value)->toBe('mail.edu-gate.uz')
        ->and(Setting::where('key', 'mail_host')->first()->is_encrypted)->toBeFalse();
});

it('keeps the SMTP password out of the audit trail', function () {
    Settings::put(['mail_password' => 'the-real-password']);

    $audit = Setting::where('key', 'mail_password')->first()->audits()->latest()->first();

    // Encrypting the column is pointless if the value is copied into `audits`.
    expect(json_encode($audit?->getModified() ?? []))->not->toContain('the-real-password');
});

it('overrides the mail config once a host is set', function () {
    expect(config('mail.default'))->toBe('array');   // phpunit.xml default

    Settings::put([
        'mail_host' => 'mail.edu-gate.uz',
        'mail_port' => '465',
        'mail_encryption' => 'ssl',
        'mail_username' => 'noreply@edu-gate.uz',
        'mail_password' => 'secret',
        'mail_from_address' => 'noreply@edu-gate.uz',
        'mail_from_name' => 'EduGate',
    ]);

    (new RuntimeConfigProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('mail.edu-gate.uz')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.encryption'))->toBe('ssl')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret')
        ->and(config('mail.from.address'))->toBe('noreply@edu-gate.uz');
});

it('leaves .env in charge while nothing is configured', function () {
    $before = config('mail.default');

    (new RuntimeConfigProvider(app()))->boot();

    // Half-configured mail that silently fails is worse than none at all.
    expect(config('mail.default'))->toBe($before);
});

it('maps "none" encryption to no encryption rather than the string', function () {
    Settings::put(['mail_host' => 'mail.edu-gate.uz', 'mail_encryption' => 'none']);

    (new RuntimeConfigProvider(app()))->boot();

    expect(config('mail.mailers.smtp.encryption'))->toBeNull();
});

it('treats an undecryptable secret as absent instead of crashing', function () {
    // What an APP_KEY rotation looks like: the ciphertext is no longer ours.
    Setting::create(['key' => 'mail_password', 'value' => 'not-valid-ciphertext', 'is_encrypted' => true]);
    Settings::flush();

    // Broken outgoing mail is recoverable; an exception on every boot is not.
    expect(Settings::get('mail_password'))->toBeNull();
});

it('survives the settings table not existing', function () {
    Schema::drop('settings');
    Settings::flush();

    // This is consulted while the framework boots, which happens during
    // `migrate` on a fresh install — before this table is created.
    expect(Settings::all())->toBe([])
        ->and(Settings::get('mail_host', 'fallback'))->toBe('fallback');

    // Booting must not throw either — an exception here bricks every command.
    (new RuntimeConfigProvider(app()))->boot();

    expect(config('mail.default'))->not->toBe('smtp');
});

it('renders the settings page', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('x'), 'is_active' => true, 'password_changed_at' => now(),
    ]);

    actingAs($admin, 'admin')->get(MailSettings::getUrl())->assertOk();
});

it('never sends the stored password back to the browser', function () {
    Settings::put(['mail_host' => 'mail.edu-gate.uz', 'mail_password' => 'the-real-password']);

    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('x'), 'is_active' => true, 'password_changed_at' => now(),
    ]);

    actingAs($admin, 'admin')
        ->get(MailSettings::getUrl())
        ->assertOk()
        ->assertDontSee('the-real-password');
});
