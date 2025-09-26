<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChapterGuidance extends Model
{
    use HasFactory;

    protected $table = 'project_chapter_guidance';

    protected $fillable = [
        'project_id',
        'chapter_guidance_id',
        'chapter_number',
        'project_specific_notes',
        'custom_elements',
        'is_completed',
        'accessed_at',
    ];

    protected $casts = [
        'custom_elements' => 'array',
        'is_completed' => 'boolean',
        'accessed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chapterGuidance(): BelongsTo
    {
        return $this->belongsTo(ChapterGuidance::class);
    }

    /**
     * Mark as accessed
     */
    public function markAccessed(): void
    {
        $this->update(['accessed_at' => now()]);
    }

    /**
     * Get guidance for specific project and chapter
     */
    public static function getForProjectChapter(int $projectId, int $chapterNumber): ?self
    {
        return self::with('chapterGuidance')
            ->where('project_id', $projectId)
            ->where('chapter_number', $chapterNumber)
            ->first();
    }
}
