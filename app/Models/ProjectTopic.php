<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'field_of_study',
        'faculty',
        'department',
        'course',
        'university',
        'academic_level',
        'geographic_focus',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

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
