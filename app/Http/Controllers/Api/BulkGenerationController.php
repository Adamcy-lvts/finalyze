<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BulkGenerateProject;
use App\Models\Project;
use App\Models\ProjectGeneration;
use App\Services\FacultyStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BulkGenerationController extends Controller
{
    public function __construct(
        private FacultyStructureService $facultyStructureService
    ) {}

    /**
     * Start or resume bulk generation
     */
    public function start(Request $request, Project $project): JsonResponse
    {
        // Validate ownership
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'resume' => 'boolean',
        ]);

        $resume = $validated['resume'] ?? false;

        // Check for existing active generation
        $existingGeneration = ProjectGeneration::where('project_id', $project->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingGeneration) {
            return response()->json([
                'message' => 'Generation already in progress',
                'generation_id' => $existingGeneration->id,
                'status' => $existingGeneration->status,
                'progress' => $existingGeneration->progress,
            ], 409);
        }

        // If resuming, find the last failed/cancelled generation
        if ($resume) {
            $generation = ProjectGeneration::where('project_id', $project->id)
                ->whereIn('status', ['failed', 'cancelled'])
                ->latest()
                ->first();

            if (! $generation) {
                return response()->json([
                    'message' => 'No generation to resume',
                ], 400);
            }

            // Reset status to pending
            $generation->update([
                'status' => 'pending',
                'message' => 'Preparing to resume generation...',
            ]);
        } else {
            // Create new generation record
            $generation = ProjectGeneration::create([
                'project_id' => $project->id,
                'status' => 'pending',
                'progress' => 0,
                'current_stage' => 'initializing',
                'message' => 'Initializing generation...',
                'details' => [],
                'metadata' => [
                    'started_at' => now()->toISOString(),
                    'total_chapters' => count($this->facultyStructureService->getChapterStructure($project)),
                ],
            ]);
        }

        // Dispatch job
        BulkGenerateProject::dispatch($generation, $resume);

        Log::info('Bulk generation started', [
            'project_id' => $project->id,
            'generation_id' => $generation->id,
            'resume' => $resume,
        ]);

        return response()->json([
            'message' => $resume ? 'Generation resumed' : 'Generation started',
            'generation_id' => $generation->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Get current generation status
     */
    public function status(Project $project): JsonResponse
    {
        // Validate ownership
        abort_if($project->user_id !== auth()->id(), 403);

        $generation = ProjectGeneration::where('project_id', $project->id)
            ->latest()
            ->first();

        if (! $generation) {
            return response()->json([
                'status' => 'not_started',
                'message' => 'No generation has been started',
            ]);
        }

        // Get chapter statuses
        $chapterStatuses = $project->chapters()
            ->orderBy('chapter_number')
            ->get()
            ->map(fn ($chapter) => [
                'chapter_number' => $chapter->chapter_number,
                'title' => $chapter->title,
                'status' => $chapter->status,
                'word_count' => $chapter->word_count,
                'target_word_count' => $chapter->target_word_count,
                'is_completed' => $chapter->status === 'completed',
            ]);

        return response()->json([
            'generation_id' => $generation->id,
            'status' => $generation->status,
            'progress' => $generation->progress,
            'current_stage' => $generation->current_stage,
            'message' => $generation->message,
            'details' => $generation->details ?? [],
            'metadata' => $generation->metadata ?? [],
            'chapter_statuses' => $chapterStatuses,
            'download_links' => $generation->status === 'completed' ? [
                'docx' => route('projects.export.docx', $project->slug),
                'pdf' => route('projects.export.pdf', $project->slug),
            ] : null,
        ]);
    }

    /**
     * Cancel an in-progress generation
     */
    public function cancel(Project $project): JsonResponse
    {
        // Validate ownership
        abort_if($project->user_id !== auth()->id(), 403);

        $generation = ProjectGeneration::where('project_id', $project->id)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if (! $generation) {
            return response()->json([
                'message' => 'No active generation to cancel',
            ], 400);
        }

        // Update status
        $generation->update([
            'status' => 'cancelled',
            'message' => 'Generation cancelled by user',
            'details' => array_merge($generation->details ?? [], [
                [
                    'timestamp' => now()->toISOString(),
                    'type' => 'info',
                    'message' => '🛑 Generation cancelled by user',
                ],
            ]),
        ]);

        // Clear any pending jobs for this generation
        // Note: This won't stop currently running jobs, but will prevent new ones from starting
        Cache::put("generation_cancelled_{$generation->id}", true, 3600);

        Log::info('Bulk generation cancelled', [
            'project_id' => $project->id,
            'generation_id' => $generation->id,
            'progress_at_cancellation' => $generation->progress,
        ]);

        return response()->json([
            'message' => 'Generation cancelled',
            'progress' => $generation->progress,
            'can_resume' => $generation->progress > 0,
        ]);
    }

    /**
     * Retry a failed generation
     */
    public function retry(Project $project): JsonResponse
    {
        return $this->start(new Request(['resume' => true]), $project);
    }
}
