<?php

return [

    'cnpja' => [
        'url' => env('CNPJA_URL', 'https://open.cnpja.com'),
        'timeout' => (int) env('CNPJA_TIMEOUT', 10),
    ],

    'brasilapi' => [
        'url' => env('BRASIL_API_URL', 'https://brasilapi.com.br/api'),
        'timeout' => (int) env('BRASIL_API_TIMEOUT', 10),
    ],

    'turnstile' => [
        'local_site_key' => env('TURNSTILE_LOCAL_SITE_KEY', '1x00000000000000000000AA'),
        'local_secret_key' => env('TURNSTILE_LOCAL_SECRET_KEY', '1x0000000000000000000000000000000AA'),
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

    'workos' => [
        'client_id' => env('WORKOS_CLIENT_ID', ''),
        'secret' => env('WORKOS_API_KEY', ''),
        'redirect_url' => env('WORKOS_REDIRECT_URL', ''),
    ],

];
