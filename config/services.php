<?php

return [

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'api' => 'https://api.telegram.org',
        'timeout' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Aloqabank A2A. Only the base URL differs between the simulator and the
     * real bank — the client, auth and parsing are identical, which is what
     * makes the simulator worth running. The driver refuses a /sim/ URL when
     * APP_ENV=production.
     */
    'aloqabank' => [
        'base_url' => env('ALOQABANK_BASE_URL', env('APP_URL', 'http://127.0.0.1:8000').'/sim/aloqabank/api/v2'),
        'username' => env('ALOQABANK_USERNAME', 'rpay'),
        'password' => env('ALOQABANK_PASSWORD', 'p@ssw0rd'),
        'service_id' => env('ALOQABANK_SERVICE_ID', 33),
        'timeout' => (int) env('ALOQABANK_TIMEOUT', 15),
    ],

];
