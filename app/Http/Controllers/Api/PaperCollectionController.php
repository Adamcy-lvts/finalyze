<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CollectPapersForProject;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaperCollectionController extends Controller
{
    /**
     * Start paper collection for a project
     */
    public function startCollection(Request $request, Project $project): JsonResponse
    {
        try {
            Log::info("Starting paper collection for project: {$project->title}", [
                'project_id' => $project->id,
                'user_id' => auth()->id(),
            ]);

            // Check if collection is already in progress
            if ($project->paper_collection_status === 'collecting_papers') {
                // Check if the collection process is stuck (older than 10 minutes)
                $lastUpdate = $project->updated_at;
                $tenMinutesAgo = now()->subMinutes(10);

                if ($lastUpdate < $tenMinutesAgo) {
                    Log::warning('Paper collection appears stuck, auto-resetting', [
                        'project_id' => $project->id,
                        'last_update' => $lastUpdate,
                        'minutes_ago' => $lastUpdate->diffInMinutes(now()),
                    ]);

                    // Auto-reset stuck collection
                    $project->update([
                        'paper_collection_status' => null,
                        'paper_collection_message' => null,
                    ]);

                    // Clear cache
                    Cache::forget("paper_collection_status_{$project->id}");
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Source collection is already in progress for this project.',
                        'started_minutes_ago' => $lastUpdate->diffInMinutes(now()),
                    ], 409);
                }
            }

            // Validate that project has a topic
            if (empty($project->topic)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project must have a topic before collecting sources.',
                ], 422);
            }

            // Update project status to indicate collection started
            $project->update([
                'paper_collection_status' => 'collecting_papers',
                'paper_collection_message' => 'Starting source collection...',
                'citation_guaranteed' => true, // Enable citation guarantee mode
            ]);

            // Dispatch the job
            CollectPapersForProject::dispatch($project, $request->boolean('force_refresh', false));

            return response()->json([
                'success' => true,
                'message' => 'Source collection started successfully.',
                'status' => 'collecting_papers',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start paper collection: '.$e->getMessage(), [
                'project_id' => $project->id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start source collection: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get paper collection status for a project
     */
    public function getStatus(Project $project): JsonResponse
    {
        $cacheKey = "paper_collection_status_{$project->id}";

        // Get cached status for real-time updates
        $cachedStatus = Cache::get($cacheKey);

        $response = [
            'project_id' => $project->id,
            'status' => $project->paper_collection_status ?? 'not_started',
            'message' => $project->paper_collection_message,
            'count' => $project->paper_collection_count,
            'completed_at' => $project->paper_collection_completed_at,
            'citation_guaranteed' => $project->citation_guaranteed,
        ];

        // Merge with cached data if available (for real-time job updates)
        if ($cachedStatus && is_array($cachedStatus)) {
            $response = array_merge($response, $cachedStatus);
        }

        // Add preview papers if available in cache
        if (isset($cachedStatus['papers'])) {
            $response['preview_papers'] = $cachedStatus['papers'];
        }

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }

    /**
     * Get collected papers for a project
     */
    public function getPapers(Project $project): JsonResponse
    {
        if ($project->paper_collection_status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Source collection is not completed for this project.',
            ], 422);
        }

        // Get papers from collected_papers table
        $collectedPapers = $project->collectedPapers()
            ->recent() // Only recent collections (last 7 days)
            ->forProject($project->id)
            ->get();

        $papers = $collectedPapers->map(function ($paper) {
            return [
                'id' => $paper->id,
                'title' => $paper->title,
                'authors' => $paper->authors,
                'year' => $paper->year,
                'venue' => $paper->venue,
                'doi' => $paper->doi,
                'url' => $paper->url,
                'abstract' => $paper->abstract,
                'citation_count' => $paper->citation_count,
                'quality_score' => $paper->quality_score,
                'source_api' => $paper->source_api,
                'is_open_access' => $paper->is_open_access,
                'collected_at' => $paper->collected_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'papers' => $papers,
                'total' => $papers->count(),
                'collection_completed_at' => $project->paper_collection_completed_at,
            ],
        ]);
    }

    /**
     * Reset paper collection (for re-collection)
     */
    public function resetCollection(Project $project): JsonResponse
    {
        try {
            $project->update([
                'paper_collection_status' => null,
                'paper_collection_message' => null,
                'paper_collection_count' => 0,
                'paper_collection_completed_at' => null,
                'citation_guaranteed' => false,
            ]);

            // Clear cache
            $cacheKey = "paper_collection_status_{$project->id}";
            Cache::forget($cacheKey);

            Log::info("Paper collection reset for project: {$project->title}");

            return response()->json([
                'success' => true,
                'message' => 'Source collection has been reset. You can now start a new collection.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset paper collection: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset source collection.',
            ], 500);
        }
    }
}
