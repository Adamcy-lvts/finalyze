<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Signup Bonus
    |--------------------------------------------------------------------------
    |
    | Number of free words given to new users upon registration.
    |
    */
    'signup_bonus_words' => env('SIGNUP_BONUS_WORDS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Low Balance Warning Threshold
    |--------------------------------------------------------------------------
    |
    | Word balance threshold below which users receive a low balance warning.
    |
    */
    'low_balance_threshold' => env('LOW_BALANCE_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Word Estimation Multipliers
    |--------------------------------------------------------------------------
    |
    | Used to estimate word consumption for different features.
    |
    */
    'estimation' => [
        // Add buffer to chapter word estimates (1.1 = 10% buffer)
        'chapter_buffer' => 1.1,

        // Average words per AI suggestion
        'suggestion_words' => 200,

        // Average words per chat response
        'chat_words' => 500,

        // Average words for defense questions (per chapter)
        'defense_words' => 1000,

        // Average words for content expansion
        'expand_words' => 300,

        // Average words for rephrasing
        'rephrase_words' => 150,
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Word Thresholds
    |--------------------------------------------------------------------------
    |
    | Minimum word balance required to perform certain actions.
    |
    */
    'minimum_balance' => [
        'chapter_generation' => 500,
        'ai_suggestion' => 100,
        'chat' => 200,
        'defense' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Refund Policy
    |--------------------------------------------------------------------------
    */
    'refunds' => [
        // Auto-refund words if generation fails
        'auto_refund_on_failure' => true,

        // Partial refund percentage if generation is significantly under target
        'partial_refund_threshold' => 0.5, // Refund if less than 50% of target
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'code' => 'NGN',
        'symbol' => '₦',
        'name' => 'Nigerian Naira',
    ],
];
