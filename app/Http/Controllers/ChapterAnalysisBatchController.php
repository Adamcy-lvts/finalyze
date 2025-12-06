<?php

namespace App\Http\Controllers;

use App\Jobs\RunChapterAnalysisBatchJob;
use App\Models\Chapter;
use App\Models\ChapterAnalysisBatch;
use App\Models\ChapterAnalysisBatchItem;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChapterAnalysisBatchController extends Controller
{
    public function start(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'chapter_ids' => 'sometimes|array',
            'chapter_ids.*' => 'integer|exists:chapters,id',
        ]);

        // Determine which chapters to include
        $chaptersQuery = $project->chapters()->orderBy('chapter_number');
        if (! empty($validated['chapter_ids'])) {
            $chaptersQuery->whereIn('id', $validated['chapter_ids']);
        }
        $chapters = $chaptersQuery->get();

        if ($chapters->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No chapters selected for analysis.',
            ], 422);
        }

        // Rough word cost estimate (reuse chapter word count)
        $estimatedWords = $chapters->sum(fn (Chapter $chapter) => max(500, str_word_count($chapter->content ?? '')));

        $batch = DB::transaction(function () use ($project, $chapters, $estimatedWords) {
            $batch = ChapterAnalysisBatch::create([
                'project_id' => $project->id,
                'status' => 'queued',
                'total_chapters' => $chapters->count(),
                'required_words' => $estimatedWords,
            ]);

            foreach ($chapters as $chapter) {
                ChapterAnalysisBatchItem::create([
                    'batch_id' => $batch->id,
                    'chapter_id' => $chapter->id,
                    'status' => 'queued',
                ]);
            }

            return $batch;
        });

        RunChapterAnalysisBatchJob::dispatch($batch);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
        ]);
    }

    public function show(Project $project, ChapterAnalysisBatch $batch): JsonResponse
    {
        abort_if($project->user_id !== auth()->id(), 403);
        abort_if($batch->project_id !== $project->id, 403);

        $batch->load(['items.chapter', 'items.analysisResult']);

        return response()->json([
            'success' => true,
            'batch' => $batch,
        ]);
    }

    public function results(Project $project): JsonResponse
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $chapters = $project->chapters()
            ->with(['latestAnalysis' => function ($query) {
                $query->select('id', 'chapter_id', 'total_score', 'completion_percentage', 'analyzed_at');
            }])
            ->orderBy('chapter_number')
            ->get(['id', 'chapter_number', 'title', 'word_count']);

        return response()->json([
            'success' => true,
            'chapters' => $chapters,
        ]);
    }
}
