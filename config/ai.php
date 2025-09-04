<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for AI content generation providers.
    | You can customize provider priority, model preferences, and fallback behavior.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Provider Priority Order
    |--------------------------------------------------------------------------
    |
    | Define the order in which AI providers should be attempted.
    | The first available provider in this list will be used as default.
    |
    | Available providers: 'openai', 'claude', 'gemini', 'together'
    |
    */
    'provider_priority' => [
        'openai',  // Primary - fastest and most reliable
        'claude',  // Secondary - high quality backup
        // 'gemini',  // Tertiary - Google's offering
        // 'together', // Quaternary - cost-effective option
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Models per Content Type
    |--------------------------------------------------------------------------
    |
    | Specify which models to use for different types of content.
    | This allows fine-tuning cost vs quality for each content type.
    |
    */
    'content_models' => [
        'introduction' => [
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'max_tokens' => 3000,
        ],
        'conclusion' => [
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'max_tokens' => 3000,
        ],
        'literature_review' => [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.6,
            'max_tokens' => 4000,
        ],
        'methodology' => [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.6,
            'max_tokens' => 4000,
        ],
        'general' => [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ],
        'draft' => [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.8,
            'max_tokens' => 2000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Settings
    |--------------------------------------------------------------------------
    |
    | Configure how the system handles provider failures.
    |
    */
    'failover' => [
        'max_retries' => 3,              // Maximum attempts per provider
        'retry_delay_seconds' => 2,      // Delay between retries
        'health_check_interval' => 300,  // Check provider health every 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Control
    |--------------------------------------------------------------------------
    |
    | Set limits and preferences for cost management.
    |
    */
    'cost_control' => [
        'prefer_cheaper_models' => env('AI_PREFER_CHEAPER_MODELS', false),
        'max_cost_per_request' => env('AI_MAX_COST_PER_REQUEST', 0.50), // $0.50 max per request
        'monthly_budget_limit' => env('AI_MONTHLY_BUDGET', 100.00),     // $100/month limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Settings
    |--------------------------------------------------------------------------
    |
    | Configure quality vs speed preferences.
    |
    */
    'quality' => [
        'high_quality_chapters' => [1, 6], // Use premium models for these chapters
        'standard_chapters' => [2, 3, 4, 5], // Use standard models for these
        'enable_quality_boost' => env('AI_ENABLE_QUALITY_BOOST', true),
    ],
];
