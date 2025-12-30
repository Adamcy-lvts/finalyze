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

    'semantic_scholar' => [
        'api_key' => env('SEMANTIC_SCHOLAR_API_KEY'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'skills_model' => env('ANTHROPIC_SKILLS_MODEL', 'claude-sonnet-4-5-20250929'),
    ],

    'pptx' => [
        'engine' => env('PPTX_ENGINE', 'pptxgenjs'),
        'node_binary' => env('NODE_BINARY', 'node'),
    ],

    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),
    ],

];
