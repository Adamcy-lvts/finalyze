<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\PaperCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CollectPapersForProject implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout

    public int $tries = 1; // Single try to avoid confusion with retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project,
        public bool $forceRefresh = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaperCollectionService $paperService): void
    {
        try {
            Log::info("Starting paper collection for project: {$this->project->title}");

            // Initialize progress tracking
            $this->updateSourceProgress('initializing', 'Starting source collection from academic databases...', 0, []);

            // Collect papers with source-specific progress tracking
            $papers = $this->collectPapersWithProgress($paperService);

            if ($papers->isEmpty()) {
                Log::warning("No papers collected for project: {$this->project->title}");
                $this->updateProjectStatus('collection_failed', 'No sources found for your topic. Please try a different topic or keywords.');

                return;
            }

            // Update progress: storing sources
            $this->updateSourceProgress('storing', 'Storing collected sources in database...', 90, $papers->take(5)->toArray());

            // Store papers in collected_papers table
            $paperService->storePapersForProject($this->project, $papers);

            // Final completion
            $this->project->update([
                'paper_collection_status' => 'completed',
                'paper_collection_count' => $papers->count(),
                'paper_collection_completed_at' => now(),
            ]);

            // Cache final success state
            $this->updateSourceProgress('completed', "Successfully collected {$papers->count()} sources", 100, $papers->take(5)->toArray());

            Log::info("Successfully collected {$papers->count()} papers for project: {$this->project->title}");

        } catch (\Exception $e) {
            Log::error("Paper collection failed for project {$this->project->title}: ".$e->getMessage(), [
                'project_id' => $this->project->id,
                'error' => $e->getTraceAsString(),
            ]);

            $this->updateProjectStatus('collection_failed', 'Failed to collect papers: '.$e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Collect papers with detailed source progress tracking
     */
    protected function collectPapersWithProgress(PaperCollectionService $paperService): \Illuminate\Support\Collection
    {
        $allPapers = collect();
        $sources = ['semantic_scholar', 'openalex', 'crossref', 'pubmed'];
        $baseProgress = 10; // Start at 10%
        $progressPerSource = 20; // 20% per source (80% total for collection)

        foreach ($sources as $index => $source) {
            $currentProgress = $baseProgress + ($index * $progressPerSource);

            try {
                // Update UI with current source
                $this->updateSourceProgress('collecting',
                    "Searching {$this->getSourceDisplayName($source)}...",
                    $currentProgress,
                    $allPapers->take(3)->toArray(),
                    $source
                );

                // Collect from this source
                $sourcePapers = $this->collectFromSource($paperService, $source);
                $allPapers = $allPapers->merge($sourcePapers);

                // Update progress after source completion
                $this->updateSourceProgress('collecting',
                    "Found {$sourcePapers->count()} sources from {$this->getSourceDisplayName($source)}",
                    $currentProgress + ($progressPerSource * 0.8),
                    $allPapers->take(5)->toArray(),
                    $source
                );

                Log::info("Collected {$sourcePapers->count()} papers from {$source} for project: {$this->project->title}");

            } catch (\Exception $e) {
                Log::warning("Failed to collect from {$source}: ".$e->getMessage());

                $this->updateSourceProgress('collecting',
                    "{$this->getSourceDisplayName($source)} search failed, continuing with other sources...",
                    $currentProgress + $progressPerSource,
                    $allPapers->take(5)->toArray(),
                    $source
                );

                continue; // Continue with other sources
            }
        }

        // Deduplicate and rank sources
        $this->updateSourceProgress('processing', 'Removing duplicates and ranking sources...', 85, $allPapers->take(5)->toArray());
        $uniquePapers = $paperService->deduplicateAndRank($allPapers);

        return $uniquePapers;
    }

    /**
     * Collect papers from a specific source
     */
    protected function collectFromSource(PaperCollectionService $paperService, string $source): \Illuminate\Support\Collection
    {
        $topic = $this->project->topic;

        return match ($source) {
            'semantic_scholar' => $paperService->collectFromSemanticScholar($topic),
            'openalex' => $paperService->collectFromOpenAlex($topic),
            'crossref' => $paperService->collectFromCrossRef($topic),
            'pubmed' => $paperService->collectFromPubMed($topic),
            default => collect()
        };
    }

    /**
     * Get display name for source
     */
    protected function getSourceDisplayName(string $source): string
    {
        return match ($source) {
            'semantic_scholar' => 'Semantic Scholar',
            'openalex' => 'OpenAlex',
            'crossref' => 'CrossRef',
            'pubmed' => 'PubMed',
            default => ucfirst($source)
        };
    }

    /**
     * Update source-specific progress with detailed feedback
     */
    protected function updateSourceProgress(string $status, string $message, int $percentage, array $papers = [], ?string $currentSource = null): void
    {
        $cacheKey = "paper_collection_status_{$this->project->id}";

        $progressData = [
            'status' => $status,
            'message' => $message,
            'percentage' => min(100, max(0, $percentage)),
            'current_source' => $currentSource,
            'papers_preview' => $papers,
            'papers_count' => count($papers),
            'updated_at' => now()->toISOString(),
            'sources_completed' => $this->getCompletedSources($percentage),
        ];

        Cache::put($cacheKey, $progressData, 3600);

        // Also update project status for persistence
        $this->project->update([
            'paper_collection_status' => $status === 'completed' ? 'completed' : 'collecting_papers',
            'paper_collection_message' => $message,
        ]);

        Log::info("Paper collection progress: {$percentage}% - {$message}", [
            'project_id' => $this->project->id,
            'source' => $currentSource,
        ]);
    }

    /**
     * Determine which sources have been completed based on progress percentage
     */
    protected function getCompletedSources(int $percentage): array
    {
        $sources = [];
        if ($percentage >= 30) {
            $sources[] = 'semantic_scholar';
        }
        if ($percentage >= 50) {
            $sources[] = 'openalex';
        }
        if ($percentage >= 70) {
            $sources[] = 'crossref';
        }
        if ($percentage >= 90) {
            $sources[] = 'pubmed';
        }

        return $sources;
    }

    /**
     * Update project collection status
     */
    protected function updateProjectStatus(string $status, ?string $message = null): void
    {
        $this->project->update([
            'paper_collection_status' => $status,
            'paper_collection_message' => $message,
        ]);

        // Update cache for real-time UI updates
        $cacheKey = "paper_collection_status_{$this->project->id}";
        Cache::put($cacheKey, [
            'status' => $status,
            'message' => $message,
            'updated_at' => now()->toISOString(),
        ], 3600);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Paper collection job failed permanently for project: {$this->project->title}", [
            'project_id' => $this->project->id,
            'error' => $exception->getMessage(),
        ]);

        $this->updateProjectStatus('collection_failed', 'Source collection failed after multiple attempts.');
    }
}
