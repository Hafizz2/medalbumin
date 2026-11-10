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

    // Login codes. "telegram" = Telegram Gateway, "log" = written to storage/logs (local development).
    'otp' => [
        'driver' => env('OTP_DRIVER', 'log'),
        // Fixed login for App Store / Play Store reviewers.
        'review_phone' => env('OTP_REVIEW_PHONE'),
        'review_code' => env('OTP_REVIEW_CODE'),
        'review_email' => env('OTP_REVIEW_EMAIL'),
    ],

    // https://gateway.telegram.org — Account → API token
    'telegram_gateway' => [
        'token' => env('TELEGRAM_GATEWAY_TOKEN'),
        'sender_username' => env('TELEGRAM_GATEWAY_SENDER'), // optional verified channel
        'reports' => env('TELEGRAM_GATEWAY_REPORTS', true),  // delivery reports to /webhooks/telegram-gateway
    ],

    // "Log In With Telegram" — @BotFather → bot → Login Widget
    'telegram' => [
        'client_id' => env('TELEGRAM_CLIENT_ID'),
        'client_secret' => env('TELEGRAM_CLIENT_SECRET'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        // Where the app receives its login ticket. Expo Go uses exp:// during development.
        'app_return_default' => env('TELEGRAM_APP_RETURN', 'medalbumin://auth/telegram'),
        'app_return_schemes' => ['medalbumin://', 'exp://', 'exps://'],
    ],

    'chapa' => [
        'secret_key' => env('CHAPA_SECRET_KEY'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
    ],

    'google' => [
        // OAuth client IDs of the iOS, Android and web apps (comma separated).
        'client_ids' => array_filter(explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))),
    ],

    'apple' => [
        // iOS bundle id (and Services ID if Sign in with Apple is used elsewhere).
        'client_ids' => array_filter(explode(',', (string) env('APPLE_CLIENT_IDS', ''))),
    ],

    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

    'support' => [
        'telegram' => env('TELEGRAM_SUPPORT_URL', 'https://t.me/mednexus_support'),
        'email' => env('SUPPORT_EMAIL', 'support@medalbumin.com'),
    ],

];
