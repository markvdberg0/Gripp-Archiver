<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Bankkoppeling via een Ponto custom integration (alleen lezen). Zie docs/bankkoppeling-ponto.md.
    'ponto' => [
        'client_id' => env('PONTO_CLIENT_ID'),
        'client_secret' => env('PONTO_CLIENT_SECRET'),
        'cert' => env('PONTO_CERT_PATH'),
        'key' => env('PONTO_KEY_PATH'),
        'passphrase' => env('PONTO_KEY_PASSPHRASE'),
        'url' => env('PONTO_API_URL', 'https://api.ibanity.com/ponto-connect'),
        'max_pages' => (int) env('PONTO_MAX_PAGES', 50), // per rekening per run, 100 transacties per pagina
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
