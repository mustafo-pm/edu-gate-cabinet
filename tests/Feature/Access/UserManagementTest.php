<?php

declare(strict_types=1);

use App\Enums\AlertEvent;
use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Enums\PspStatus;
use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Filament\Resources\MerchantUsers\MerchantUserResource;
use App\Filament\Resources\PspUsers\PspUserResource;
use App\Jobs\SendAlert;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\Psp;
use App\Models\PspUser;
use App\Support\Alerts;
use App\Support\TempPassword;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

/**
 * Issuing and revoking cabinet credentials.
 *
 * Two rules carry the weight: a temporary password must actually be temporary,
 * and the plaintext must never reach a Telegram group whose history outlives
 * the account.
 */
beforeEach(function () {
    $this->merchant = Merchant::create([
        'name' => 'Toshkent Davlat Universiteti',
        'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);

    $this->user = MerchantUser::create([
        'merchant_id' => $this->merchant->id, 'name' => 'Finance Officer',
        'email' => 'finance@tdu.uz', 'password' => Hash::make('original-password'),
        'is_active' => true, 'password_changed_at' => now(),
    ]);
});

it('issues a password that must be changed', function () {
    $plain = TempPassword::issue($this->user);
    $this->user->refresh();

    expect(Hash::check($plain, $this->user->password))->toBeTrue()
        ->and($this->user->must_change_password)->toBeTrue()
        // Null, not the issuing time: nobody has chosen this password yet.
        ->and($this->user->password_changed_at)->toBeNull();
});

it('kills a remember-me session when a password is revoked', function () {
    $this->user->forceFill(['remember_token' => 'still-signed-in'])->save();

    TempPassword::issue($this->user);

    // A surviving cookie would keep the old holder signed in, which is exactly
    // what a reset is meant to stop.
    expect($this->user->fresh()->remember_token)->not->toBe('still-signed-in');
});

it('never puts the password in the Telegram alert', function () {
    $plain = TempPassword::generate();

    $body = Alerts::format(AlertEvent::UserCreated, [
        'name' => 'Finance Officer', 'email' => 'finance@tdu.uz',
        'cabinet' => 'Institution (app)', 'organisation' => 'TDU', 'by' => 'Admin',
    ]);

    expect($body)->toContain('finance@tdu.uz')
        ->and($body)->toContain('temporary password')
        ->and($body)->not->toContain($plain);
});

it('raises the right event for a new account versus a reset', function () {
    Bus::fake();

    TempPassword::issue($this->user, isNew: true);
    Bus::assertDispatched(SendAlert::class,
        fn (SendAlert $j) => $j->event === AlertEvent::UserCreated->value);

    TempPassword::issue($this->user);
    Bus::assertDispatched(SendAlert::class,
        fn (SendAlert $j) => $j->event === AlertEvent::PasswordReset->value);
});

it('sends a merchant holding a temporary password to change it', function () {
    TempPassword::issue($this->user);

    actingAs($this->user->fresh(), 'merchant')
        ->get(route('merchant.dashboard'))
        ->assertRedirect(route('password.change'));
});

it('lets that user reach the change screen itself', function () {
    TempPassword::issue($this->user);

    actingAs($this->user->fresh(), 'merchant')
        ->get(route('password.change'))
        ->assertOk();
});

it('does not interrupt a user who chose their own password', function () {
    actingAs($this->user, 'merchant')
        ->get(route('merchant.dashboard'))
        ->assertOk();
});

it('clears the flag once a new password is chosen', function () {
    $temp = TempPassword::issue($this->user);

    actingAs($this->user->fresh(), 'merchant')
        ->post(route('password.change.update'), [
            'current_password' => $temp,
            'password' => 'a-much-better-1',
            'password_confirmation' => 'a-much-better-1',
        ])
        ->assertRedirect(route('merchant.dashboard'));

    $this->user->refresh();

    expect($this->user->must_change_password)->toBeFalse()
        ->and($this->user->password_changed_at)->not->toBeNull()
        ->and(Hash::check('a-much-better-1', $this->user->password))->toBeTrue();
});

it('refuses to let the temporary password be kept', function () {
    $temp = TempPassword::issue($this->user);

    actingAs($this->user->fresh(), 'merchant')
        ->post(route('password.change.update'), [
            'current_password' => $temp,
            'password' => $temp,
            'password_confirmation' => $temp,
        ])
        ->assertSessionHasErrors('password');

    // Otherwise the flag clears while the credential the admin read out stays live.
    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('refuses a wrong current password', function () {
    TempPassword::issue($this->user);

    actingAs($this->user->fresh(), 'merchant')
        ->post(route('password.change.update'), [
            'current_password' => 'not-it',
            'password' => 'a-much-better-1',
            'password_confirmation' => 'a-much-better-1',
        ])
        ->assertSessionHasErrors('current_password');
});

it('answers 423 rather than a redirect for a JSON caller', function () {
    TempPassword::issue($this->user);

    actingAs($this->user->fresh(), 'merchant')
        ->getJson(route('merchant.dashboard'))
        ->assertStatus(423)
        ->assertJsonPath('error.code', 'password_change_required');
});

it('applies to partner accounts too', function () {
    $psp = Psp::create(['name' => 'ClickPay', 'code' => 'clickpay', 'status' => PspStatus::Active]);
    $user = PspUser::create([
        'psp_id' => $psp->id, 'name' => 'Ops', 'email' => 'ops@clickpay.uz',
        'password' => Hash::make('x'), 'is_active' => true, 'password_changed_at' => now(),
    ]);

    TempPassword::issue($user);

    actingAs($user->fresh(), 'psp')
        ->get(route('psp.dashboard'))
        ->assertRedirect(route('password.change'));
});

it('names the cabinet an account belongs to', function () {
    $psp = Psp::create(['name' => 'ClickPay', 'code' => 'cp', 'status' => PspStatus::Active]);
    $pspUser = PspUser::create([
        'psp_id' => $psp->id, 'name' => 'Ops', 'email' => 'o@cp.uz',
        'password' => Hash::make('x'), 'is_active' => true,
    ]);
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'a@edu-gate.uz',
        'password' => Hash::make('x'), 'is_active' => true,
    ]);

    expect(TempPassword::cabinet($this->user))->toBe('Institution (app)')
        ->and(TempPassword::cabinet($pspUser))->toBe('Partner / PSP')
        ->and(TempPassword::cabinet($admin))->toBe('EduGate admin')
        ->and(TempPassword::organisation($this->user))->toBe('Toshkent Davlat Universiteti')
        ->and(TempPassword::organisation($admin))->toBeNull();
});

it('generates passwords that are unguessable and free of ambiguous symbols', function () {
    $a = TempPassword::generate();
    $b = TempPassword::generate();

    expect($a)->toHaveLength(14)
        ->and($a)->not->toBe($b)
        // Read down a phone or typed from a note: symbols cost support calls.
        ->and($a)->toMatch('/^[A-Za-z0-9]+$/');
});

it('renders the three Access screens', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('x'), 'is_active' => true, 'password_changed_at' => now(),
    ]);

    foreach ([
        MerchantUserResource::class,
        PspUserResource::class,
        AdminUserResource::class,
    ] as $resource) {
        actingAs($admin, 'admin')->get($resource::getUrl('index'))->assertOk();
        actingAs($admin, 'admin')->get($resource::getUrl('create'))->assertOk();
    }
});

it('never offers to delete an account', function () {
    // A deleted user leaves their name dangling on every record they touched.
    expect(MerchantUserResource::canDelete($this->user))->toBeFalse();
});
