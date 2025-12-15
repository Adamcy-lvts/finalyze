<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterProgressGuidance extends Model
{
    use HasFactory;

    protected $table = 'chapter_progress_guidances';

    protected $fillable = [
        'user_id',
        'project_id',
        'chapter_id',
        'chapter_number',
        'fingerprint',
        'stage',
        'stage_label',
        'completion_percentage',
        'contextual_tip',
        'next_steps',
        'writing_milestones',
        'completed_step_ids',
        'meta',
    ];

    protected $casts = [
        'next_steps' => 'array',
        'writing_milestones' => 'array',
        'completed_step_ids' => 'array',
        'meta' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}

