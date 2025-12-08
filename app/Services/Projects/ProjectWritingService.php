<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Transformers\ChapterTransformer;
use App\Transformers\OutlineTransformer;
use App\Transformers\ProjectTransformer;

class ProjectWritingService
{
    public function writingPayload(Project $project): array
    {
        $project->load([
            'chapters',
            'category',
            'outlines.sections',
            'universityRelation',
            'facultyRelation.structure.chapters',
            'departmentRelation',
        ]);

        $facultyStructureChapters = $project->facultyRelation?->structure?->chapters?->map(function ($chapter) {
            return [
                'chapter_number' => $chapter->chapter_number,
                'chapter_title' => $chapter->chapter_title,
                'target_word_count' => $chapter->target_word_count,
                'completion_threshold' => $chapter->completion_threshold,
                'is_required' => $chapter->is_required,
            ];
        })->values();

        return [
            'project' => array_merge(
                ProjectTransformer::forTopicSelection($project),
                [
                    'mode' => $project->mode,
                    'university' => $project->universityRelation?->name,
                    'faculty' => $project->faculty_name,
                    'facultyStructureChapters' => $facultyStructureChapters,
                    'department' => $project->department_name,
                    'progress' => $project->getProgressPercentage(),
                    'chapters' => ChapterTransformer::collection($project->chapters),
                    'outlines' => OutlineTransformer::collection($project->outlines),
                ],
            ),
            'targetWordCount' => $project->category?->target_word_count ?? 15000,
        ];
    }
}
