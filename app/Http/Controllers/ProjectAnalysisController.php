<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Inertia\Inertia;

class ProjectAnalysisController extends Controller
{
    public function index(Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $project->load(['chapters' => function ($query) {
            $query->orderBy('chapter_number')->select('id', 'project_id', 'chapter_number', 'title', 'content', 'word_count', 'status');
        }]);

        return Inertia::render('projects/BulkAnalysis', [
            'project' => $project,
        ]);
    }
}
