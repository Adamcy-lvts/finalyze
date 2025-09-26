<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacultyChapter extends Model
{
    protected $fillable = [
        'faculty_structure_id',
        'academic_level',
        'project_type',
        'chapter_number',
        'chapter_title',
        'description',
        'target_word_count',
        'completion_threshold',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'target_word_count' => 'integer',
        'completion_threshold' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the faculty structure this chapter belongs to
     */
    public function facultyStructure(): BelongsTo
    {
        return $this->belongsTo(FacultyStructure::class);
    }

    /**
     * Get all sections for this chapter
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FacultySection::class)->orderBy('sort_order');
    }

    /**
     * Scope to filter by academic level
     */
    public function scopeForLevel($query, string $academicLevel)
    {
        return $query->where('academic_level', $academicLevel);
    }

    /**
     * Scope to filter by project type
     */
    public function scopeForProjectType($query, string $projectType)
    {
        return $query->where('project_type', $projectType);
    }

    /**
     * Scope to get required chapters only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }
}
