<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

/**
 * What the browser's password manager sees.
 *
 * One host serves three cabinets behind one sign-in page, so a password manager
 * keys every saved credential on the same origin. Without a username field it
 * cannot tell the accounts apart, and changing one password overwrites the
 * saved entry for another — leaving two accounts locked out at once. That is
 * not hypothetical; it is what these fields are here to stop.
 */
it('names the username field on the sign-in form', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('autocomplete="username"', escape: false)
        ->assertSee('autocomplete="current-password"', escape: false);
});

it('says which account the password change is for', function () {
    $merchant = Merchant::create([
        'name' => 'TDU', 'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);

    $user = MerchantUser::create([
        'merchant_id' => $merchant->id, 'name' => 'Officer', 'email' => 'finance@tdu.uz',
        'password' => Hash::make('original-password'), 'is_active' => true,
        'must_change_password' => true,
    ]);

    actingAs($user, 'merchant')
        ->get('/password/change')
        ->assertOk()
        // For the browser: this is what tells it which saved entry to update.
        ->assertSee('autocomplete="username"', escape: false)
        // For the person: whose password am I actually changing?
        ->assertSee('finance@tdu.uz');
});

it('shows the admin their own address, not the last one used on this host', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('original-password'), 'is_active' => true,
        'must_change_password' => true,
    ]);

    actingAs($admin, 'admin')
        ->get('/password/change')
        ->assertOk()
        ->assertSee('admin@edu-gate.uz')
        ->assertDontSee('finance@tdu.uz');
});

it('ignores a username posted with the form', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('original-password'), 'is_active' => true,
        'must_change_password' => true,
    ]);

    // The field exists for the password manager, never as an instruction:
    // posting somebody else's address must not change whose password moves.
    actingAs($admin, 'admin')
        ->post('/password/change', [
            'username' => 'someone-else@edu-gate.uz',
            'current_password' => 'original-password',
            'password' => 'brand-new-passw0rd',
            'password_confirmation' => 'brand-new-passw0rd',
        ])->assertRedirect();

    expect(Hash::check('brand-new-passw0rd', $admin->fresh()->password))->toBeTrue();
});
