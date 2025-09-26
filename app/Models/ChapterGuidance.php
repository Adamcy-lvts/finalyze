<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChapterGuidance extends Model
{
    use HasFactory;

    protected $table = 'chapter_guidance';

    protected $fillable = [
        'course',
        'faculty',
        'field_of_study',
        'academic_level',
        'chapter_number',
        'chapter_title',
        'writing_guidance',
        'key_elements',
        'requirements',
        'tips',
        'methodology_guidance',
        'data_guidance',
        'analysis_guidance',
        'sections',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'key_elements' => 'array',
        'requirements' => 'array',
        'tips' => 'array',
        'sections' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * Find guidance for specific context
     */
    public static function findForContext(string $course, string $faculty, string $fieldOfStudy, string $academicLevel, int $chapterNumber): ?self
    {
        return self::where('course', $course)
            ->where('faculty', $faculty)
            ->where('field_of_study', $fieldOfStudy)
            ->where('academic_level', $academicLevel)
            ->where('chapter_number', $chapterNumber)
            ->first();
    }

    /**
     * Increment usage tracking
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }
}
