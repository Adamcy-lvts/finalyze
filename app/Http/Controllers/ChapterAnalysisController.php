<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Services\ChapterAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChapterAnalysisController extends Controller
{
    public function __construct(
        private ChapterAnalysisService $analysisService
    ) {}

    /**
     * Analyze a chapter and return quality metrics
     */
    public function analyze(int $chapter): JsonResponse
    {
        try {
            $chapterModel = Chapter::findOrFail($chapter);

            Log::info('Chapter analysis requested', [
                'chapter_id' => $chapterModel->id,
                'project_id' => $chapterModel->project_id,
                'user_id' => auth()->id(),
            ]);

            // Check if user can access this chapter
            if ($chapterModel->project->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Perform comprehensive analysis
            $result = $this->analysisService->analyzeChapter($chapterModel);

            return response()->json([
                'success' => true,
                'analysis' => [
                    'id' => $result->id,
                    'total_score' => $result->total_score,
                    'quality_level' => $result->getQualityLevel(),
                    'meets_threshold' => $result->passesCompletionThreshold(),
                    'meets_defense_requirement' => $result->meets_defense_requirement,
                    'scores' => $result->getScoreBreakdown(),
                    'metrics' => [
                        'word_count' => $result->word_count,
                        'paragraph_count' => $result->paragraph_count,
                        'sentence_count' => $result->sentence_count,
                        'citation_count' => $result->citation_count,
                        'verified_citation_count' => $result->verified_citation_count,
                        'completion_percentage' => $result->completion_percentage,
                        'reading_time_minutes' => $result->reading_time_minutes,
                    ],
                    'detailed_feedback' => [
                        'grammar_issues' => $result->grammar_issues ?? [],
                        'readability_metrics' => $result->readability_metrics ?? [],
                        'structure_feedback' => $result->structure_feedback ?? [],
                        'citation_analysis' => $result->citation_analysis ?? [],
                    ],
                    'suggestions' => $result->getFormattedSuggestions(),
                    'analyzed_at' => $result->analyzed_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Chapter analysis failed', [
                'chapter_id' => $chapter,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Analysis failed. Please try again.',
                'message' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get latest analysis for a chapter
     */
    public function latest(int $chapter): JsonResponse
    {
        try {
            $chapterModel = Chapter::findOrFail($chapter);

            // Check authorization
            if ($chapterModel->project->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $latestAnalysis = $chapterModel->latestAnalysis;

            if (! $latestAnalysis) {
                return response()->json([
                    'success' => false,
                    'message' => 'No analysis found for this chapter. Run analysis first.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'analysis' => [
                    'id' => $latestAnalysis->id,
                    'total_score' => $latestAnalysis->total_score,
                    'quality_level' => $latestAnalysis->getQualityLevel(),
                    'meets_threshold' => $latestAnalysis->passesCompletionThreshold(),
                    'meets_defense_requirement' => $latestAnalysis->meets_defense_requirement,
                    'scores' => $latestAnalysis->getScoreBreakdown(),
                    'metrics' => [
                        'word_count' => $latestAnalysis->word_count,
                        'paragraph_count' => $latestAnalysis->paragraph_count,
                        'sentence_count' => $latestAnalysis->sentence_count,
                        'citation_count' => $latestAnalysis->citation_count,
                        'verified_citation_count' => $latestAnalysis->verified_citation_count,
                        'completion_percentage' => $latestAnalysis->completion_percentage,
                        'reading_time_minutes' => $latestAnalysis->reading_time_minutes,
                    ],
                    'suggestions' => $latestAnalysis->getFormattedSuggestions(),
                    'analyzed_at' => $latestAnalysis->analyzed_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get latest analysis', [
                'chapter_id' => $chapter,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve analysis.',
            ], 500);
        }
    }

    /**
     * Get analysis history for a chapter
     */
    public function history(int $chapter): JsonResponse
    {
        try {
            $chapterModel = Chapter::findOrFail($chapter);

            // Check authorization
            if ($chapterModel->project->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $analyses = $chapterModel->analysisResults()
                ->orderBy('analyzed_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($analysis) {
                    return [
                        'id' => $analysis->id,
                        'total_score' => $analysis->total_score,
                        'quality_level' => $analysis->getQualityLevel(),
                        'meets_threshold' => $analysis->passesCompletionThreshold(),
                        'word_count' => $analysis->word_count,
                        'citation_count' => $analysis->citation_count,
                        'analyzed_at' => $analysis->analyzed_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'analyses' => $analyses,
                'count' => $analyses->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get analysis history', [
                'chapter_id' => $chapter,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve analysis history.',
            ], 500);
        }
    }

    /**
     * Get quick quality overview for multiple chapters
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (! $projectId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Project ID is required.',
                ], 400);
            }

            // Verify user owns the project
            $project = auth()->user()->projects()->findOrFail($projectId);

            $chapters = $project->chapters()
                ->with('latestAnalysis')
                ->orderBy('chapter_number')
                ->get()
                ->map(function ($chapter) {
                    $analysis = $chapter->latestAnalysis;

                    return [
                        'chapter_id' => $chapter->id,
                        'chapter_number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'has_analysis' => ! is_null($analysis),
                        'total_score' => $analysis?->total_score,
                        'quality_level' => $analysis?->getQualityLevel(),
                        'meets_threshold' => $analysis?->passesCompletionThreshold(),
                        'word_count' => $analysis?->word_count ?? str_word_count(strip_tags($chapter->content ?? '')),
                        'last_analyzed' => $analysis?->analyzed_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'project_id' => $projectId,
                'chapters' => $chapters,
                'summary' => [
                    'total_chapters' => $chapters->count(),
                    'analyzed_chapters' => $chapters->where('has_analysis', true)->count(),
                    'chapters_meeting_threshold' => $chapters->where('meets_threshold', true)->count(),
                    'average_score' => $chapters->where('has_analysis', true)->avg('total_score'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get project analysis overview', [
                'project_id' => $request->get('project_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve project overview.',
            ], 500);
        }
    }
}
