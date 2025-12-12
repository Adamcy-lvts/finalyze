<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Services\Topics\TopicLibraryService;
use Inertia\Inertia;
use Inertia\Response;

class PublicTopicController extends Controller
{
    public function index(TopicLibraryService $topicService): Response
    {
        // Fetch all topics (limit to reasonable amount for initial load, or all if feasible)
        // TopicLibraryService::getAllTopics returns a collection.
        // We'll fetch a decent number, the frontend handles client-side search/filter for now
        // as per the existing TopicsIndex.vue pattern.
        $topics = $topicService->getAllTopics(300);

        $faculties = Faculty::select(['id', 'name', 'slug'])->get();

        return Inertia::render('ProjectTopics', [
            'allTopics' => $topics,
            'faculties' => $faculties,
            'meta' => [
                'totalTopics' => $topicService->countAllTopics(),
            ],
        ]);
    }
}
