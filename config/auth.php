<?php

use App\Models\AdminUser;
use App\Models\MerchantUser;
use App\Models\Psp;
use App\Models\PspUser;
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
    |   merchant → institution staff  (cabinet.edu-gate.uz/merchant)
    |   psp      → payment providers  (cabinet.edu-gate.uz/partner)
    |   admin    → EduGate internal   (cabinet.edu-gate.uz/admin)
    |   api      → PSP server-to-server via Sanctum token (api.edu-gate.uz)
    |
    | The three cabinets share one hostname and are told apart by path; only the
    | API has a host of its own. Separate guards are what keeps them isolated,
    | not separate domains, so a shared host changes nothing about the rule.
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
            'model' => MerchantUser::class,
        ],

        'psp_users' => [
            'driver' => 'eloquent',
            'model' => PspUser::class,
        ],

        'admin_users' => [
            'driver' => 'eloquent',
            'model' => AdminUser::class,
        ],

        'psps' => [
            'driver' => 'eloquent',
            'model' => Psp::class,
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
