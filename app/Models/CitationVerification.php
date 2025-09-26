<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitationVerification extends Model
{
    protected $fillable = [
        'raw_citation',
        'status',
        'session_id',
        'detected_format',
        'matched_citation_id',
        'match_confidence',
        'api_responses',
        'verification_time_ms',
    ];

    protected $casts = [
        'api_responses' => 'array',
        'match_confidence' => 'decimal:2',
    ];

    public function citation(): BelongsTo
    {
        return $this->belongsTo(Citation::class, 'matched_citation_id');
    }

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }
}
