<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterSuggestion extends Model
{
    protected $fillable = [
        'project_id',
        'project_category_id',
        'chapter_number',
        'course_field',
        'topic_keywords',
        'suggestion_type',
        'suggestion_content',
        'metadata',
        'usage_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectCategory(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class);
    }

    public function scopeForContext($query, int $categoryId, int $chapterNumber, ?string $courseField, string $topicKeywords)
    {
        return $query->where('project_category_id', $categoryId)
            ->where('chapter_number', $chapterNumber)
            ->where('course_field', $courseField)
            ->where('topic_keywords', 'like', "%{$topicKeywords}%");
    }
}
