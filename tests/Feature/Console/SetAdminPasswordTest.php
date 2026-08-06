<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

it('rotates an admin password and invalidates remember-me', function () {
    $admin = AdminUser::create([
        'name' => 'Admin', 'email' => 'admin@edu-gate.uz',
        'password' => Hash::make('password'), 'is_active' => true,
    ]);
    $admin->forceFill(['remember_token' => 'old-token'])->save();

    $this->artisan('edugate:admin-password admin@edu-gate.uz --password=NewStrongPass123')
        ->assertSuccessful();

    $admin->refresh();

    expect(Hash::check('NewStrongPass123', $admin->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse()
        // A surviving remember-me cookie would outlive the rotation.
        ->and($admin->remember_token)->not->toBe('old-token');
});

it('fails on an unknown email', function () {
    $this->artisan('edugate:admin-password nobody@edu-gate.uz')->assertFailed();
});
