<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChapterSuggestion extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'chapter_id',
        'chapter_suggestion_id',
        'suggestion_type',
        'suggestion_content',
        'trigger_reason',
        'detected_issues',
        'status',
        'shown_at',
        'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_issues' => 'array',
            'shown_at' => 'datetime',
            'actioned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function chapterSuggestion(): BelongsTo
    {
        return $this->belongsTo(ChapterSuggestion::class);
    }
}
