<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacultyStructure extends Model
{
    protected $fillable = [
        'faculty_name',
        'faculty_slug',
        'description',
        'academic_levels',
        'default_structure',
        'chapter_templates',
        'guidance_templates',
        'terminology',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'academic_levels' => 'array',
        'default_structure' => 'array',
        'chapter_templates' => 'array',
        'guidance_templates' => 'array',
        'terminology' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all projects using this faculty structure
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'faculty', 'faculty_name');
    }

    /**
     * Get all chapter templates for this faculty
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(FacultyChapter::class)->orderBy('sort_order');
    }

    /**
     * Get all section templates through chapters
     */
    public function sections()
    {
        return $this->hasManyThrough(FacultySection::class, FacultyChapter::class);
    }

    /**
     * Get structure for specific academic level
     */
    public function getStructureForLevel(string $academicLevel): array
    {
        $structure = $this->default_structure;

        // Override with level-specific structure if available
        if (isset($structure['level_overrides'][$academicLevel])) {
            $structure = array_merge_recursive(
                $structure,
                $structure['level_overrides'][$academicLevel]
            );
        }

        return $structure;
    }

    /**
     * Get chapter structure for specific project type
     */
    public function getChapterStructure(string $academicLevel, string $projectType = 'thesis'): array
    {
        $structure = $this->getStructureForLevel($academicLevel);

        return $structure['chapters'][$projectType] ?? $structure['chapters']['default'] ?? [];
    }

    /**
     * Get guidance templates for this faculty
     */
    public function getGuidanceTemplates(string $academicLevel): array
    {
        $templates = $this->guidance_templates;

        // Add level-specific guidance if available
        if (isset($templates['level_specific'][$academicLevel])) {
            $templates = array_merge_recursive(
                $templates['common'] ?? [],
                $templates['level_specific'][$academicLevel]
            );
        }

        return $templates;
    }

    /**
     * Get terminology glossary for this faculty
     */
    public function getTerminology(?string $academicLevel = null): array
    {
        $terminology = $this->terminology['common'] ?? [];

        if ($academicLevel && isset($this->terminology['level_specific'][$academicLevel])) {
            $terminology = array_merge(
                $terminology,
                $this->terminology['level_specific'][$academicLevel]
            );
        }

        return $terminology;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForFaculty($query, string $facultyName)
    {
        $normalizedName = strtolower(trim($facultyName));

        return $query->where(function ($q) use ($facultyName, $normalizedName) {
            $q->where('faculty_name', $facultyName)
                ->orWhere('faculty_slug', $facultyName)
                ->orWhere('faculty_slug', $normalizedName)
                ->orWhere('faculty_slug', rtrim($normalizedName, 's')) // Remove trailing 's' for plural forms
                ->orWhere('faculty_slug', $normalizedName.'s'); // Add 's' for singular forms
        });
    }

    /**
     * Get estimated timeline for projects in this faculty
     */
    public function getEstimatedTimeline(string $academicLevel, string $projectType = 'thesis'): array
    {
        $structure = $this->getStructureForLevel($academicLevel);

        return $structure['timeline'][$projectType] ?? $structure['timeline']['default'] ?? [
            'research_phase' => '2-3 months',
            'writing_phase' => '3-4 months',
            'review_phase' => '1-2 months',
            'total_duration' => '6-9 months',
        ];
    }
}
