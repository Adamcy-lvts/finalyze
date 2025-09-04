<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTopic extends Model
{
    protected $fillable = [
        'field_of_study',
        'faculty',
        'department',
        'course',
        'university',
        'academic_level',
        'title',
        'description',
        'difficulty',
        'timeline',
        'resource_level',
        'feasibility_score',
        'keywords',
        'research_type',
        'selection_count',
        'last_selected_at',
    ];

    protected $casts = [
        'keywords' => 'array',
        'last_selected_at' => 'timestamp',
        'selection_count' => 'integer',
        'feasibility_score' => 'integer',
    ];

    /**
     * Scope to find topics that match project criteria (exact academic context match)
     */
    public function scopeForProject($query, Project $project)
    {
        $faculty = $project->settings['faculty'] ?? null;
        $department = $project->settings['department'] ?? null;

        return $query->where('academic_level', $project->type)
            ->when($faculty, fn ($q) => $q->where('faculty', $faculty))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->when($project->course, fn ($q) => $q->where('course', $project->course))
            ->when($project->field_of_study, fn ($q) => $q->where('field_of_study', $project->field_of_study));
    }

    /**
     * Scope to find topics for similar criteria (broader match by faculty or department)
     */
    public function scopeForSimilarProjects($query, Project $project)
    {
        $faculty = $project->settings['faculty'] ?? null;
        $department = $project->settings['department'] ?? null;

        return $query->where('academic_level', $project->type)
            ->where(function ($q) use ($faculty, $department, $project) {
                // Match by faculty
                if ($faculty) {
                    $q->orWhere('faculty', $faculty);
                }
                // Match by department
                if ($department) {
                    $q->orWhere('department', $department);
                }
                // Match by field of study
                if ($project->field_of_study) {
                    $q->orWhere('field_of_study', $project->field_of_study);
                }
            });
    }

    /**
     * Scope to exclude already selected topics
     */
    public function scopeUnselected($query, array $selectedTopicTitles = [])
    {
        if (! empty($selectedTopicTitles)) {
            return $query->whereNotIn('title', $selectedTopicTitles);
        }

        return $query;
    }

    /**
     * Scope to get popular topics (most selected)
     */
    public function scopePopular($query)
    {
        return $query->orderBy('selection_count', 'desc');
    }

    /**
     * Mark topic as selected
     */
    public function markAsSelected()
    {
        $this->increment('selection_count');
        $this->update(['last_selected_at' => now()]);
    }
}
