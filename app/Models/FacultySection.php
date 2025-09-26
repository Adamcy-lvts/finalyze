<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultySection extends Model
{
    protected $fillable = [
        'faculty_chapter_id',
        'section_number',
        'section_title',
        'description',
        'writing_guidance',
        'tips',
        'target_word_count',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'tips' => 'array',
        'is_required' => 'boolean',
        'target_word_count' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the faculty chapter this section belongs to
     */
    public function facultyChapter(): BelongsTo
    {
        return $this->belongsTo(FacultyChapter::class);
    }

    /**
     * Get the faculty structure through the chapter
     */
    public function facultyStructure(): BelongsTo
    {
        return $this->facultyChapter()->facultyStructure();
    }

    /**
     * Scope to get required sections only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Get the chapter number through the relationship
     */
    public function getChapterNumberAttribute()
    {
        return $this->facultyChapter->chapter_number ?? null;
    }
}
