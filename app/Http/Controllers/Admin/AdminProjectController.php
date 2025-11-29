<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Inertia\Inertia;

class AdminProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with('user')
            ->latest()
            ->paginate(20)
            ->through(fn ($project) => [
                'id' => $project->id,
                'title' => $project->title,
                'user' => $project->user?->only('id', 'name', 'email'),
                'status' => $project->status,
                'topic_status' => $project->topic_status,
                'mode' => $project->mode,
                'type' => $project->type,
                'field_of_study' => $project->field_of_study,
                'created_at' => $project->created_at,
            ]);

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project)
    {
        $project->load(['user', 'chapters']);

        return Inertia::render('Admin/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'type' => $project->type,
                'status' => $project->status,
                'topic_status' => $project->topic_status,
                'mode' => $project->mode,
                'field_of_study' => $project->field_of_study,
                'university' => $project->university,
                'course' => $project->course,
                'user' => $project->user?->only('id', 'name', 'email'),
                'chapters' => $project->chapters->map(fn ($ch) => [
                    'id' => $ch->id,
                    'chapter_number' => $ch->chapter_number,
                    'title' => $ch->title,
                    'status' => $ch->status,
                    'word_count' => $ch->word_count,
                    'target_word_count' => $ch->target_word_count,
                ]),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ],
        ]);
    }

    public function destroy(Project $project)
    {
        return response()->json(['status' => 'ok']);
    }

    public function export(Project $project)
    {
        return response()->json(['status' => 'ok']);
    }
}
