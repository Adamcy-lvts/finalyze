<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIUsageLog extends Model
{
    /**
     * Explicitly set the table name because Laravel would otherwise
     * inflect this class to `a_i_usage_logs`.
     */
    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'user_id',
        'feature',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'request_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
