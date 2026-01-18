<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeedbackRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requestable_type',
        'requestable_id',
        'source',
        'status',
        'rating',
        'comment',
        'context',
        'shown_at',
        'dismissed_at',
        'submitted_at',
        'cooldown_until',
    ];

    protected $casts = [
        'rating' => 'integer',
        'context' => 'array',
        'shown_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'cooldown_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }
}
