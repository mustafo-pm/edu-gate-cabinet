<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | EduGate uses FOUR separate guards. Sessions are NEVER shared across roles.
    |   merchant → institution staff  (app.edu-gate.uz)
    |   psp      → payment providers  (partner.edu-gate.uz)
    |   admin    → EduGate internal   (admin.edu-gate.uz)
    |   api      → PSP server-to-server via Sanctum token (api.edu-gate.uz)
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'merchant' => [
            'driver' => 'session',
            'provider' => 'merchant_users',
        ],

        'psp' => [
            'driver' => 'session',
            'provider' => 'psp_users',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admin_users',
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => 'psps',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'merchant_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\MerchantUser::class,
        ],

        'psp_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\PspUser::class,
        ],

        'admin_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\AdminUser::class,
        ],

        'psps' => [
            'driver' => 'eloquent',
            'model' => App\Models\Psp::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'merchant_users' => [
            'provider' => 'merchant_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'psp_users' => [
            'provider' => 'psp_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'admin_users' => [
            'provider' => 'admin_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
