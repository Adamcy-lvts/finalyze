<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Services\Topics\TopicLibraryService;
use App\Transformers\ProjectTransformer;
use App\Transformers\TopicTransformer;

class ProjectReadService
{
    public function __construct(
        private TopicLibraryService $topicLibraryService,
    ) {
    }

    /**
     * Prepare topic selection payload with eager-loaded relationships and saved topics.
     */
    public function topicSelectionData(Project $project): array
    {
        $project->loadMissing([
            'universityRelation:id,name',
            'facultyRelation:id,name',
            'departmentRelation:id,name',
        ]);

        $savedTopics = $this->topicLibraryService->getSavedTopicsForProject($project);

        return [
            'project' => ProjectTransformer::forTopicSelection($project),
            'savedTopics' => TopicTransformer::collection($savedTopics),
        ];
    }
}
