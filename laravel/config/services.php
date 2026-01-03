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

    'fastapi' => [
        'url' => env('FASTAPI_URL', 'http://localhost:8000'),
        'timeout' => env('FASTAPI_TIMEOUT', 300),
    ],

    'yookassa' => [
        'shop_id' => env('YOO_KASSA_SHOP_ID') ?: env('YOOKASSA_SHOP_ID'), // Поддержка обоих вариантов
        'secret_key' => env('YOO_KASSA_SECRET_KEY') ?: env('YOOKASSA_SECRET_KEY'), // Поддержка обоих вариантов
        'test_mode' => filter_var(env('YOOKASSA_TEST_MODE', env('YOO_KASSA_TEST_MODE', true)), FILTER_VALIDATE_BOOLEAN),
    ],

];
