<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Filament\Resources\MerchantUsers\Pages\CreateMerchantUser;
use App\Livewire\Merchant\Staff;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Support\CabinetRoles;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Staff and roles inside an institution's cabinet.
 *
 * Two things are being defended. Nobody may hand themselves more access than
 * they have — otherwise "can add colleagues" quietly becomes "can move the
 * bank account". And nobody may lock the institution out of its own cabinet,
 * which only support can undo.
 */
it('lets an owner add a colleague with a temporary password', function () {
    $owner = institution();

    $component = Livewire::actingAs($owner, 'merchant')
        ->test(Staff::class)
        ->set('name', 'Buxgalter')
        ->set('email', 'buxgalter@webster.uz')
        ->set('role', CabinetRoles::ACCOUNTANT)
        ->call('invite')
        ->assertHasNoErrors();

    $new = MerchantUser::where('email', 'buxgalter@webster.uz')->first();

    expect($new->merchant_id)->toBe($owner->merchant_id)
        ->and($new->hasRole(CabinetRoles::ACCOUNTANT))->toBeTrue()
        // The difference between a temporary password and one somebody else
        // simply knows.
        ->and($new->must_change_password)->toBeTrue()
        ->and($component->get('issuedPassword'))->not->toBeNull();
});

it('shows the password once and never stores it', function () {
    $owner = institution();

    $component = Livewire::actingAs($owner, 'merchant')
        ->test(Staff::class)
        ->set('name', 'Buxgalter')->set('email', 'b@webster.uz')
        ->set('role', CabinetRoles::VIEWER)
        ->call('invite');

    $plain = $component->get('issuedPassword');
    $new = MerchantUser::where('email', 'b@webster.uz')->first();

    expect($plain)->not->toBeNull()
        ->and($new->password)->not->toBe($plain);

    $component->call('dismissPassword')->assertSet('issuedPassword', null);
});

it('will not let a non-owner mint an owner', function () {
    $owner = institution();

    $registrar = MerchantUser::create([
        'merchant_id' => $owner->merchant_id, 'name' => 'Registrar',
        'email' => 'reg@webster.uz', 'password' => 'x', 'is_active' => true,
        'password_changed_at' => now(),
    ]);
    // Widened deliberately: even someone allowed to add colleagues must not be
    // able to promote themselves into the role that moves bank accounts.
    $registrar->assignRole(CabinetRoles::REGISTRAR);
    $registrar->givePermissionTo(CabinetRoles::STAFF);

    Livewire::actingAs($registrar->fresh(), 'merchant')
        ->test(Staff::class)
        ->set('name', 'Sneaky')->set('email', 'sneaky@webster.uz')
        ->set('role', CabinetRoles::OWNER)
        ->call('invite')
        ->assertForbidden();

    expect(MerchantUser::where('email', 'sneaky@webster.uz')->exists())->toBeFalse();
});

it('will not let anyone change their own role', function () {
    $owner = institution();

    Livewire::actingAs($owner, 'merchant')
        ->test(Staff::class)
        ->call('changeRole', $owner->id, CabinetRoles::VIEWER)
        ->assertForbidden();

    expect($owner->fresh()->hasRole(CabinetRoles::OWNER))->toBeTrue();
});

it('will not let anyone switch themselves off', function () {
    $owner = institution();

    Livewire::actingAs($owner, 'merchant')
        ->test(Staff::class)
        ->call('toggleActive', $owner->id)
        ->assertForbidden();

    expect($owner->fresh()->is_active)->toBeTrue();
});

it('will not demote the last owner', function () {
    $owner = institution();

    $second = MerchantUser::create([
        'merchant_id' => $owner->merchant_id, 'name' => 'Second',
        'email' => 'second@webster.uz', 'password' => 'x', 'is_active' => true,
        'password_changed_at' => now(),
    ]);
    $second->assignRole(CabinetRoles::OWNER);

    // An institution with no owner cannot appoint one — only support can.
    Livewire::actingAs($second, 'merchant')
        ->test(Staff::class)
        ->call('changeRole', $owner->id, CabinetRoles::VIEWER)
        ->assertHasNoErrors();

    expect($owner->fresh()->hasRole(CabinetRoles::VIEWER))->toBeTrue();

    // Now only `second` is left holding it.
    Livewire::actingAs($owner->fresh(), 'merchant')
        ->test(Staff::class)
        ->call('changeRole', $second->id, CabinetRoles::VIEWER)
        ->assertForbidden();
});

it('never reaches a colleague at another institution', function () {
    $ours = institution();
    $theirs = institution('Rival', 'finance@rival.uz');

    // MerchantUser carries no tenant global scope, so the merchant_id filter
    // in colleague() is the isolation. Over HTTP the handler turns this miss
    // into a 404.
    expect(fn () => Livewire::actingAs($ours, 'merchant')
        ->test(Staff::class)
        ->call('resetPassword', $theirs->id)
    )->toThrow(ModelNotFoundException::class);

    expect($theirs->fresh()->must_change_password)->toBeFalsy();
});

// ── What the roles actually gate ────────────────────────────────────────

it('keeps an accountant away from bank accounts and staff', function () {
    $user = institution(role: CabinetRoles::ACCOUNTANT);

    actingAs($user, 'merchant')->get('/merchant/payments')->assertOk();
    actingAs($user, 'merchant')->get('/merchant/bank-accounts')->assertForbidden();
    actingAs($user, 'merchant')->get('/merchant/accounts')->assertForbidden();
    actingAs($user, 'merchant')->get('/merchant/profile')->assertForbidden();
});

it('keeps a viewer out of anything that changes something', function () {
    $user = institution(role: CabinetRoles::VIEWER);

    actingAs($user, 'merchant')->get('/merchant/students')->assertOk();
    actingAs($user, 'merchant')->get('/merchant/schedules')->assertForbidden();
    actingAs($user, 'merchant')->get('/merchant/bank-accounts')->assertForbidden();
});

it('leaves the dashboard reachable for every role', function () {
    foreach (CabinetRoles::assignable() as $i => $role) {
        $user = institution('Inst '.$i, "user{$i}@x.uz", $role);

        // Signing in and landing on a 403 would look like a broken account.
        actingAs($user, 'merchant')->get('/merchant')->assertOk();
    }
});

it('hides links the signed-in user cannot follow', function () {
    $user = institution(role: CabinetRoles::ACCOUNTANT);

    actingAs($user, 'merchant')->get('/merchant')
        ->assertOk()
        ->assertDontSee(__('cabinet.bank_accounts.title'))
        ->assertDontSee(__('cabinet.staff.title'));
});

it('gives an admin-created cabinet account a role it can use', function () {
    CabinetRoles::sync();

    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'ops@edu-gate.uz',
        'password' => Hash::make('x'),
        'is_active' => true, 'password_changed_at' => now(),
    ]);

    $merchant = Merchant::create([
        'name' => 'New University', 'type' => MerchantType::University,
        'status' => MerchantStatus::Active,
    ]);

    actingAs($admin, 'admin');

    $user = MerchantUser::create([
        'merchant_id' => $merchant->id, 'name' => 'First Officer',
        'email' => 'first@new.uz', 'password' => 'x', 'is_active' => true,
    ]);

    (new CreateMerchantUser)
        ->assignDefaultRoleForTesting($user);

    // Onboarding hands over one account; it has to be able to add the others.
    expect($user->fresh()->hasRole(CabinetRoles::OWNER))->toBeTrue();
});
