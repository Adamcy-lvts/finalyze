<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    |
    | Control what gets written to the ActivityLog table.
    |
    */
    'ai_endpoints' => (bool) env('ACTIVITY_LOG_AI_ENDPOINTS', true),
    'ai_provider_calls' => (bool) env('ACTIVITY_LOG_AI_PROVIDER_CALLS', true),
    'bulk_jobs' => (bool) env('ACTIVITY_LOG_BULK_JOBS', true),
];

