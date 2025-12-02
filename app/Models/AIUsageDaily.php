<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIUsageDaily extends Model
{
    protected $table = 'ai_usage_daily';

    protected $fillable = [
        'date',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
