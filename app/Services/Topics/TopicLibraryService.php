<?php

namespace App\Services\Topics;

use App\Models\Project;
use App\Models\ProjectTopic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TopicLibraryService
{
    public function getSavedTopicsForProject(Project $project, int $limit = 10): Collection
    {
        $query = ProjectTopic::query()
            ->select([
                'id',
                'title',
                'description',
                'difficulty',
                'timeline',
                'resource_level',
                'feasibility_score',
                'keywords',
                'research_type',
                'field_of_study',
                'faculty',
                'course',
                'academic_level',
            ]);

        return $query
            ->where('user_id', $project->user_id)
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getAllTopics(?int $limit = null, int $page = 1): Collection
    {
        $limit = $limit ?? 100;
        $limit = max(1, min($limit, 300));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        return Cache::remember("topics:all:limit:{$limit}:page:{$page}", 300, function () use ($limit, $offset) {
            return ProjectTopic::query()
                ->select([
                    'id',
                    'title',
                    'description',
                    'difficulty',
                    'timeline',
                    'resource_level',
                    'feasibility_score',
                    'keywords',
                    'research_type',
                    'field_of_study',
                    'faculty',
                    'course',
                    'academic_level',
                    'created_at',
                ])
                ->orderByDesc('created_at')
                ->skip($offset)
                ->take($limit)
                ->get();
        });
    }

    public function countAllTopics(): int
    {
        return Cache::remember('topics:all:count', 300, function () {
            return (int) ProjectTopic::count();
        });
    }
}
