<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefenseFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'overall_score',
        'strengths',
        'weaknesses',
        'question_performance',
        'recommendations',
        'generated_at',
    ];

    protected $casts = [
        'overall_score' => 'integer',
        'strengths' => 'array',
        'weaknesses' => 'array',
        'question_performance' => 'array',
        'generated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(DefenseSession::class, 'session_id');
    }
}
