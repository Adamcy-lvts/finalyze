<?php

namespace App\Services\Projects;

use App\Http\Requests\Projects\TopicSelectionRequest;
use App\Models\Project;
use App\Services\Topics\TopicLibraryService;
use App\Services\Topics\TopicTextService;
use App\Transformers\ProjectTransformer;

class ProjectTopicService
{
    public function __construct(
        private TopicLibraryService $topicLibraryService,
        private TopicTextService $topicTextService,
    ) {
    }

    public function topicSelectionPayload(Project $project, TopicSelectionRequest $request): array
    {
        $project->loadMissing([
            'universityRelation:id,name',
            'facultyRelation:id,name',
            'departmentRelation:id,name',
        ]);

        $savedTopics = $this->topicLibraryService
            ->getSavedTopicsForProject($project)
            ->map(function ($topic) {
                $description = $this->topicTextService->cleanTopicDescription($topic->description ?? '');

                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $description,
                    'difficulty' => $topic->difficulty ?? 'Intermediate',
                    'timeline' => $topic->timeline ?? '6-9 months',
                    'resource_level' => $topic->resource_level ?? 'Medium',
                    'feasibility_score' => $topic->feasibility_score ?? 0,
                    'keywords' => $topic->keywords ?? [],
                    'research_type' => $topic->research_type ?? 'Applied Research',
                    'field_of_study' => $topic->field_of_study ?? 'General',
                    'faculty' => $topic->faculty,
                    'course' => $topic->course ?? '',
                    'academic_level' => $topic->academic_level ?? 'undergraduate',
                    'literature_score' => $topic->literature_score,
                    'literature_count' => $topic->literature_count,
                    'literature_quality' => $topic->literature_quality,
                ];
            })
            ->values()
            ->all();

        return [
            'project' => ProjectTransformer::forTopicSelection($project),
            'savedTopics' => $savedTopics,
        ];
    }
}
