<?php

namespace App\Services\Projects;

use App\Http\Requests\Projects\TopicSelectionRequest;
use App\Models\Project;
use App\Services\Topics\TopicLibraryService;
use App\Transformers\ProjectTransformer;
use App\Transformers\TopicTransformer;

class ProjectTopicService
{
    public function __construct(
        private TopicLibraryService $topicLibraryService,
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
            ->pipe(fn($topics) => TopicTransformer::collection($topics));

        return [
            'project' => ProjectTransformer::forTopicSelection($project),
            'savedTopics' => $savedTopics,
        ];
    }
}
