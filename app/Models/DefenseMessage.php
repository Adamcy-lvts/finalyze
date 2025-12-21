<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefenseMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'role',
        'panelist_persona',
        'is_follow_up',
        'content',
        'audio_url',
        'audio_duration_seconds',
        'tokens_used',
        'response_time_ms',
        'ai_feedback',
    ];

    protected $casts = [
        'audio_duration_seconds' => 'decimal:2',
        'tokens_used' => 'integer',
        'response_time_ms' => 'integer',
        'ai_feedback' => 'array',
        'is_follow_up' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(DefenseSession::class, 'session_id');
    }
}
