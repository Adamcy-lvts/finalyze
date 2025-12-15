<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model pricing (USD per 1K tokens)
    |--------------------------------------------------------------------------
    |
    | Keep this map up to date for the models you use. These values are used
    | to estimate cost and convert OpenAI wallet balance into token capacity.
    |
    */
    'model_pricing' => [
        'gpt-4o' => [
            'prompt' => 0.005,
            'completion' => 0.015,
        ],
        'gpt-4o-mini' => [
            'prompt' => 0.00015,
            'completion' => 0.0006,
        ],
        'gpt-4-turbo' => [
            'prompt' => 0.01,
            'completion' => 0.03,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Word to token conversion
    |--------------------------------------------------------------------------
    |
    | Conservative factor to approximate tokens from stored word credits.
    |
    */
    'words_to_tokens_factor' => 1.5,

    /*
    |--------------------------------------------------------------------------
    | Alert thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'wallet_usd_low' => 5.00,          // Warn when available USD drops below this
        'liability_ratio' => 1.2,          // liability tokens / wallet tokens
        'runway_days_warning' => 7,        // warn if projected runway below this
        'runway_days_critical' => 3,       // critical if below this
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts toggle
    |--------------------------------------------------------------------------
    */
    'alerts_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Billing endpoints
    |--------------------------------------------------------------------------
    */
    'billing_base' => env('OPENAI_BILLING_BASE', 'https://api.openai.com'),

    /*
    |--------------------------------------------------------------------------
    | Feature flags (AI assistant)
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Progressive guidance is experimental; keep disabled for release by default.
        'progressive_guidance' => env('PROGRESSIVE_GUIDANCE_ENABLED', false),
    ],
];
