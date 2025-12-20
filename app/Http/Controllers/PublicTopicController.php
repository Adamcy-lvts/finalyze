<?php

namespace App\Http\Controllers;

use App\Models\ProjectTopic;
use App\Models\Faculty;
use App\Services\Topics\TopicLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic_id' => ['required', 'integer', 'exists:project_topics,id'],
        ]);

        $topic = ProjectTopic::query()
            ->select(['id', 'title', 'description'])
            ->findOrFail($data['topic_id']);

        $request->session()->put('project_topic_prefill', [
            'id' => $topic->id,
            'title' => $topic->title,
            'description' => $topic->description,
        ]);

        if (Auth::check()) {
            return redirect()->route('projects.create');
        }

        $request->session()->put('url.intended', route('projects.create'));

        return redirect()->route('register');
    }
}
