<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Generation Timeouts
    |--------------------------------------------------------------------------
    |
    | Configure timeout values for various AI generation jobs.
    | Values are in seconds.
    |
    */

    'timeouts' => [
        'chapter_generation' => env('AI_CHAPTER_TIMEOUT', 1200), // 20 minutes
        'bulk_generation' => env('AI_BULK_TIMEOUT', 3600), // 1 hour
        'html_conversion' => env('AI_HTML_TIMEOUT', 600), // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue names and priorities for AI jobs.
    |
    */

    'queues' => [
        'chapter_generation' => env('AI_CHAPTER_QUEUE', 'high'),
        'bulk_generation' => env('AI_BULK_QUEUE', 'default'),
        'html_conversion' => env('AI_HTML_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for AI jobs.
    |
    */

    'retries' => [
        'chapter_generation' => env('AI_CHAPTER_RETRIES', 3),
        'bulk_generation' => env('AI_BULK_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel Literature Mining
    |--------------------------------------------------------------------------
    |
    | Enable parallel API calls during literature mining for better performance.
    | Reduces literature mining time by ~70% by executing API calls concurrently.
    |
    */

    'parallel_literature_mining' => env('AI_PARALLEL_MINING', true),

];
