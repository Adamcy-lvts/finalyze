<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ChapterGuidanceService;
use App\Services\FacultyStructureService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProjectGuidanceController extends Controller
{
    public function __construct(
        private FacultyStructureService $facultyStructureService,
        private ChapterGuidanceService $chapterGuidanceService
    ) {}

    /**
     * Show project guidance overview with AI-powered chapter guidance
     */
    public function index(Project $project)
    {
        // Get complete project structure
        $structure = $this->facultyStructureService->getCompleteProjectStructure($project);
        $guidanceTemplates = $this->facultyStructureService->getGuidanceTemplates($project);
        $terminology = $this->facultyStructureService->getTerminology($project);
        $timeline = $this->facultyStructureService->getTimelineRecommendations($project);

        // Check if guidance already exists in database
        $existingGuidance = \App\Models\ProjectChapterGuidance::where('project_id', $project->id)
            ->with('chapterGuidance')
            ->get()
            ->keyBy('chapter_number');

        $chaptersWithGuidance = [];
        $hasAnyGuidance = $existingGuidance->isNotEmpty();

        if (isset($structure['chapters']) && is_array($structure['chapters'])) {
            foreach ($structure['chapters'] as $chapter) {
                $chapterNumber = $chapter['number'];

                // Check if guidance exists for this chapter
                if ($existingGuidance->has($chapterNumber)) {
                    // Load existing guidance from database
                    $projectGuidance = $existingGuidance->get($chapterNumber);
                    $guidance = $projectGuidance->chapterGuidance;

                    $chapterGuidance = [
                        'writing_guidance' => $guidance->writing_guidance,
                        'key_elements' => $guidance->key_elements,
                        'requirements' => $guidance->requirements,
                        'tips' => $guidance->tips,
                        'methodology_guidance' => $guidance->methodology_guidance,
                        'data_guidance' => $guidance->data_guidance,
                        'analysis_guidance' => $guidance->analysis_guidance,
                        'sections' => $guidance->sections ?? [],
                        'project_specific_notes' => $projectGuidance->project_specific_notes,
                        'custom_elements' => $projectGuidance->custom_elements,
                        'is_completed' => $projectGuidance->is_completed,
                    ];
                } else {
                    // No guidance exists - will be generated later
                    $chapterGuidance = null;
                }

                $chaptersWithGuidance[] = array_merge($chapter, [
                    'ai_guidance' => $chapterGuidance,
                    'has_guidance' => $chapterGuidance !== null,
                ]);
            }
        }

        // Update structure with chapters
        $structure['chapters'] = $chaptersWithGuidance;

        return Inertia::render('projects/Guidance', [
            'project' => $project->load(['user', 'outlines.sections']),
            'structure' => $structure,
            'guidance' => $guidanceTemplates,
            'terminology' => $terminology,
            'timeline' => $timeline,
            'facultyName' => $project->faculty,
            'hasAnyGuidance' => $hasAnyGuidance,
            'guidanceStatus' => $hasAnyGuidance ? 'loaded' : 'missing',
        ]);
    }

    /**
     * Get chapter-specific guidance
     */
    public function chapterGuidance(Project $project, int $chapterNumber)
    {
        $chapterStructure = $this->facultyStructureService->getChapterStructure($project);

        // Find the specific chapter
        $chapter = collect($chapterStructure)->firstWhere('number', $chapterNumber);

        if (! $chapter) {
            abort(404, 'Chapter not found');
        }

        $guidanceTemplates = $this->facultyStructureService->getGuidanceTemplates($project);
        $terminology = $this->facultyStructureService->getTerminology($project);

        return response()->json([
            'chapter' => $chapter,
            'guidance' => $guidanceTemplates,
            'terminology' => $terminology,
        ]);
    }

    /**
     * Get faculty-specific writing guidelines
     */
    public function writingGuidelines(Project $project)
    {
        $guidanceTemplates = $this->facultyStructureService->getGuidanceTemplates($project);
        $terminology = $this->facultyStructureService->getTerminology($project);

        return response()->json([
            'guidelines' => $guidanceTemplates,
            'terminology' => $terminology,
            'faculty' => $project->faculty,
        ]);
    }

    /**
     * Regenerate AI guidance for a specific chapter
     */
    public function regenerateChapterGuidance(Project $project, int $chapterNumber)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Get chapter structure to validate chapter exists
            $chapterStructure = $this->facultyStructureService->getChapterStructure($project);
            $chapter = collect($chapterStructure)->firstWhere('number', $chapterNumber);

            if (! $chapter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chapter not found',
                ], 404);
            }

            // Delete existing project-specific guidance for this chapter
            \App\Models\ProjectChapterGuidance::where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->delete();

            // Generate new guidance
            $newGuidance = $this->chapterGuidanceService->getChapterGuidance(
                $project,
                $chapterNumber,
                $chapter['title']
            );

            return response()->json([
                'success' => true,
                'message' => 'Chapter guidance regenerated successfully',
                'guidance' => $newGuidance,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to regenerate chapter guidance', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate guidance. Please try again.',
            ], 500);
        }
    }

    /**
     * Stream real-time progress for bulk guidance generation
     */
    public function streamBulkGeneration(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Set headers for Server-Sent Events
        return response()->stream(function () use ($project) {
            // Initialize output buffering if not already active
            if (ob_get_level() == 0) {
                ob_start();
            }

            echo 'data: '.json_encode(['type' => 'start', 'message' => 'Starting bulk generation...'])."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            try {
                // Get complete chapter structure
                $structure = $this->facultyStructureService->getCompleteProjectStructure($project);

                if (! isset($structure['chapters']) || ! is_array($structure['chapters'])) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => 'No chapters found'])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    return;
                }

                $chapters = $structure['chapters'];
                $totalChapters = count($chapters);

                // Delete all existing project-specific guidance
                \App\Models\ProjectChapterGuidance::where('project_id', $project->id)->delete();

                $results = [];
                $successCount = 0;

                // Generate guidance for each chapter with real-time updates
                foreach ($chapters as $index => $chapter) {
                    $chapterNumber = $chapter['number'];
                    $chapterTitle = $chapter['title'];
                    $progress = round((($index + 1) / $totalChapters) * 100, 1);

                    // Send progress update
                    echo 'data: '.json_encode([
                        'type' => 'progress',
                        'progress' => $progress,
                        'current_chapter' => $chapterNumber,
                        'chapter_title' => $chapterTitle,
                        'message' => "Generating guidance for Chapter {$chapterNumber}...",
                    ])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    try {
                        $guidance = $this->chapterGuidanceService->getChapterGuidance(
                            $project,
                            $chapterNumber,
                            $chapterTitle
                        );

                        $result = [
                            'chapter_number' => $chapterNumber,
                            'chapter_title' => $chapterTitle,
                            'success' => true,
                            'guidance' => $guidance,
                        ];

                        $results[] = $result;
                        $successCount++;

                        // Send chapter completion update
                        echo 'data: '.json_encode([
                            'type' => 'chapter_complete',
                            'result' => $result,
                            'progress' => $progress,
                        ])."\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();

                        Log::info('Stream generation: Chapter completed', [
                            'project_id' => $project->id,
                            'chapter_number' => $chapterNumber,
                            'progress' => $progress,
                        ]);

                    } catch (\Exception $e) {
                        $result = [
                            'chapter_number' => $chapterNumber,
                            'chapter_title' => $chapterTitle,
                            'success' => false,
                            'error' => $e->getMessage(),
                        ];

                        $results[] = $result;

                        // Send chapter error update
                        echo 'data: '.json_encode([
                            'type' => 'chapter_error',
                            'result' => $result,
                            'progress' => $progress,
                        ])."\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();

                        Log::error('Stream generation: Chapter failed', [
                            'project_id' => $project->id,
                            'chapter_number' => $chapterNumber,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Small delay to prevent overwhelming the API
                    usleep(500000); // 0.5 seconds
                }

                // Send completion update
                echo 'data: '.json_encode([
                    'type' => 'complete',
                    'results' => $results,
                    'summary' => [
                        'total_chapters' => $totalChapters,
                        'successful' => $successCount,
                        'failed' => $totalChapters - $successCount,
                    ],
                    'message' => "Generated guidance for {$successCount} out of {$totalChapters} chapters",
                ])."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

            } catch (\Exception $e) {
                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'Failed to generate guidance: '.$e->getMessage(),
                ])."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                Log::error('Stream bulk generation failed', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
        ]);
    }

    /**
     * Regenerate guidance for all chapters
     */
    public function regenerateAllGuidance(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Get complete chapter structure
            $structure = $this->facultyStructureService->getCompleteProjectStructure($project);

            if (! isset($structure['chapters']) || ! is_array($structure['chapters'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No chapters found for this project',
                ], 400);
            }

            $chapters = $structure['chapters'];
            $results = [];
            $totalChapters = count($chapters);

            // Delete all existing project-specific guidance
            \App\Models\ProjectChapterGuidance::where('project_id', $project->id)->delete();

            // Generate guidance for each chapter
            foreach ($chapters as $index => $chapter) {
                $chapterNumber = $chapter['number'];
                $chapterTitle = $chapter['title'];

                try {
                    $guidance = $this->chapterGuidanceService->getChapterGuidance(
                        $project,
                        $chapterNumber,
                        $chapterTitle
                    );

                    $results[] = [
                        'chapter_number' => $chapterNumber,
                        'chapter_title' => $chapterTitle,
                        'success' => true,
                        'guidance' => $guidance,
                        'progress' => round((($index + 1) / $totalChapters) * 100, 1),
                    ];

                    Log::info('Bulk generation: Chapter completed', [
                        'project_id' => $project->id,
                        'chapter_number' => $chapterNumber,
                        'progress' => round((($index + 1) / $totalChapters) * 100, 1),
                    ]);

                } catch (\Exception $e) {
                    Log::error('Bulk generation: Chapter failed', [
                        'project_id' => $project->id,
                        'chapter_number' => $chapterNumber,
                        'error' => $e->getMessage(),
                    ]);

                    $results[] = [
                        'chapter_number' => $chapterNumber,
                        'chapter_title' => $chapterTitle,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'progress' => round((($index + 1) / $totalChapters) * 100, 1),
                    ];
                }
            }

            $successCount = count(array_filter($results, fn ($r) => $r['success']));

            return response()->json([
                'success' => true,
                'message' => "Generated guidance for {$successCount} out of {$totalChapters} chapters",
                'results' => $results,
                'summary' => [
                    'total_chapters' => $totalChapters,
                    'successful' => $successCount,
                    'failed' => $totalChapters - $successCount,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk guidance generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate guidance. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Proceed to writing phase after reviewing guidance
     */
    public function proceedToWriting(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Ensure project is in guidance status
        $projectStatus = $project->status instanceof \BackedEnum ? $project->status->value : $project->status;
        if ($projectStatus !== 'guidance') {
            return response()->json([
                'success' => false,
                'message' => 'Project is not in guidance phase',
            ], 400);
        }

        // Update project status to writing
        $project->update(['status' => 'writing']);

        return response()->json([
            'success' => true,
            'message' => 'Proceeding to writing phase',
            'redirect_url' => route('projects.writing', $project->slug),
        ]);
    }
}
