<?php

declare(strict_types=1);

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Support\CabinetRoles;
use Illuminate\Support\Facades\Hash;

/*
 * An institution with one signed-in member of staff.
 *
 * Lives here rather than in one test file because several suites need it: a
 * helper declared inside a test file only exists while that file is running.
 */
function institution(
    string $name = 'Webster University',
    string $email = 'finance@webster.uz',
    string $role = CabinetRoles::OWNER,
): MerchantUser {
    CabinetRoles::sync();

    $merchant = Merchant::create([
        'name' => $name, 'type' => MerchantType::University, 'status' => MerchantStatus::Active,
    ]);

    $user = MerchantUser::create([
        'merchant_id' => $merchant->id, 'name' => 'Finance Officer', 'email' => $email,
        'password' => Hash::make('secret-password'), 'is_active' => true,
        'password_changed_at' => now(),
    ]);

    // Every cabinet screen is gated on a permission now, so a user without a
    // role can reach nothing — which is the point, and why the deploy has to
    // run edugate:roles.
    $user->assignRole($role);

    return $user;
}
