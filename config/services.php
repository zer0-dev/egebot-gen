<?php

return [

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vkapi' => [
        'access_token' => env('VK_ACCESS_TOKEN'),
        'confirmation_code' => env('VK_CONFIRMATION_CODE'),
        'secret' => env('VK_SECRET'),
    ],

    'tgapi' => [
        'token' => env('TG_TOKEN'),
    ],

    'maxapi' => [
        'token' => env('MAX_TOKEN'),
        'secret' => env('MAX_SECRET')
    ],

    'admin' => [
        'type' => env('ADMIN_TYPE'),
        'id' => env('ADMIN_ID')
    ],

    'payment' => [
        'terminal_key' => env('PAYMENT_TERMINAL_KEY'),
        'password' => env('PAYMENT_PASSWORD'),
    ]
];
