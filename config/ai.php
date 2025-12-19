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

    /*
    |--------------------------------------------------------------------------
    | Paper/source collection
    |--------------------------------------------------------------------------
    |
    | Controls how many papers are pulled from each upstream source during
    | "literature mining" / "paper collection" and how many survive ranking.
    |
    */
    'paper_collection' => [
        // Per-source limits (before dedupe/ranking)
        'limits' => [
            'semantic_scholar' => (int) env('AI_PAPER_LIMIT_SEMANTIC_SCHOLAR', 15),
            'openalex' => (int) env('AI_PAPER_LIMIT_OPENALEX', 20),
            'arxiv' => (int) env('AI_PAPER_LIMIT_ARXIV', 15),
            'crossref' => (int) env('AI_PAPER_LIMIT_CROSSREF', 20),
            'pubmed' => (int) env('AI_PAPER_LIMIT_PUBMED', 8),
        ],

        // Final max papers stored/used after dedupe + ranking
        'max_papers' => (int) env('AI_PAPER_MAX_PAPERS', 60),

        // Minimum quality threshold used in dedupeAndRank()
        'min_quality_score' => (float) env('AI_PAPER_MIN_QUALITY', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt source injection (citations)
    |--------------------------------------------------------------------------
    |
    | Controls how many verified sources are injected into the chapter prompt.
    | This is separate from how many are collected/stored.
    |
    */
    'prompt_injection' => [
        'max_papers' => (int) env('AI_PROMPT_SOURCES_MAX', 12),
        'max_papers_by_chapter_type' => [
            'literature_review' => (int) env('AI_PROMPT_SOURCES_MAX_LITREV', 25),
        ],
        'abstract_max_chars' => (int) env('AI_PROMPT_SOURCE_ABSTRACT_CHARS', 300),
    ],
];
