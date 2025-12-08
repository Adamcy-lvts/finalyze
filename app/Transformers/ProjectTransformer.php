<?php

namespace App\Transformers;

use App\Models\Project;

class ProjectTransformer
{
    public static function forTopicSelection(Project $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'title' => $project->title,
            'topic' => $project->topic,
            'description' => $project->description,
            'type' => $project->type,
            'status' => $project->status,
            'field_of_study' => $project->field_of_study,
            'course' => $project->course,
            'university' => $project->universityRelation?->name,
            'faculty' => $project->facultyRelation?->name,
            'department' => $project->departmentRelation?->name,
        ];
    }
}
