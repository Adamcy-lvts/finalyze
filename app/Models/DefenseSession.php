<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefenseSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'mode',
        'status',
        'selected_panelists',
        'difficulty_level',
        'time_limit_minutes',
        'question_limit',
        'session_duration_seconds',
        'questions_asked',
        'started_at',
        'completed_at',
        'performance_metrics',
        'readiness_score',
        'words_consumed',
    ];

    protected $casts = [
        'selected_panelists' => 'array',
        'performance_metrics' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'time_limit_minutes' => 'integer',
        'question_limit' => 'integer',
        'session_duration_seconds' => 'integer',
        'questions_asked' => 'integer',
        'readiness_score' => 'integer',
        'words_consumed' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DefenseMessage::class, 'session_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(DefenseFeedback::class, 'session_id');
    }
}
