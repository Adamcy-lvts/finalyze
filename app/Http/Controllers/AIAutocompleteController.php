<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\AIAutocompleteService;
use Illuminate\Http\Request;

class AIAutocompleteController extends Controller
{
    public function __construct(private AIAutocompleteService $service) {}

    public function autocomplete(Request $request, Project $project, int $chapterNumber)
    {
        abort_if($project->mode !== 'manual', 403, 'This endpoint is for manual mode only');
        abort_if($project->user_id !== $request->user()?->id, 403);

        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();

        $validated = $request->validate([
            'text_before' => 'required|string|max:2000',
            'text_after' => 'nullable|string|max:1000',
            'chapter_number' => 'nullable|integer|min:1|max:50',
            'chapter_title' => 'nullable|string|max:255',
            'chapter_outline' => 'nullable|string|max:2000',
            'section_heading' => 'nullable|string|max:255',
            'section_outline' => 'nullable|string|max:2000',
            'project_topic' => 'nullable|string|max:2000',
        ]);

        $result = $this->service->generateCompletion(
            $validated['text_before'],
            (string) ($validated['text_after'] ?? ''),
            (int) ($validated['chapter_number'] ?? $chapter->chapter_number),
            (string) ($validated['chapter_title'] ?? $chapter->title ?? ''),
            (string) ($validated['chapter_outline'] ?? ''),
            (string) ($validated['section_heading'] ?? ''),
            (string) ($validated['section_outline'] ?? ''),
            (string) ($validated['project_topic'] ?? $project->topic ?? ''),
        );

        return response()->json($result);
    }
}
