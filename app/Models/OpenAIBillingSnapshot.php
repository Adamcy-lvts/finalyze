<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAIBillingSnapshot extends Model
{
    protected $table = 'openai_billing_snapshots';

    protected $fillable = [
        'granted_usd',
        'used_usd',
        'available_usd',
        'expires_at',
        'period_start',
        'period_end',
        'raw',
        'fetched_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'expires_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'fetched_at' => 'datetime',
    ];
}
