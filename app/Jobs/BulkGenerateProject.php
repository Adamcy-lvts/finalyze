<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectGeneration;
use App\Services\FacultyStructureService;
use App\Services\GenerationBroadcaster;
use App\Services\PaperCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkGenerateProject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    public function __construct(
        public ProjectGeneration $generation,
        public bool $resume = false
    ) {}

    public function handle(
        PaperCollectionService $paperCollectionService,
        FacultyStructureService $facultyStructureService,
        GenerationBroadcaster $broadcaster
    ): void {
        $project = $this->generation->project;
        $chapterStructure = $facultyStructureService->getChapterStructure($project);
        $totalChapters = count($chapterStructure);

        try {
            if (config('activity.bulk_jobs', true)) {
            ActivityLog::record(
                'ai.bulk_generation.started',
                "Bulk generation started for project: ".($project->title ?: "Project #{$project->id}"),
                $this->generation,
                $project->user,
                [
                    'project_id' => $project->id,
                    'generation_id' => $this->generation->id,
                    'resume' => $this->resume,
                    'total_chapters' => $totalChapters,
                ]
            );
            }

            // Broadcast generation started
            $broadcaster->started(
                $this->generation,
                $totalChapters,
                $this->resume
            );

            // Stage 1: Literature Mining (0-20%)
            if ($this->resume && $project->collectedPapers()->count() > 0) {
                Log::info('Resuming: Skipping literature mining as papers exist', [
                    'project_id' => $project->id,
                ]);

                $broadcaster->literatureMining(
                    $this->generation,
                    'cache',
                    $project->collectedPapers()->count(),
                    $project->collectedPapers()->count(),
                    20,
                    'Literature mining already completed (using cached papers)'
                );
            } else {
                $this->runLiteratureMining($project, $paperCollectionService, $broadcaster);
            }

            // Stage 2: Chapter Generation (20-95%)
            $this->startChapterGeneration($project, $chapterStructure, $broadcaster);

            Log::info('Bulk generation dispatch completed', ['project_id' => $project->id]);

            if (config('activity.bulk_jobs', true)) {
            ActivityLog::record(
                'ai.bulk_generation.dispatched',
                "Bulk generation dispatched chapter jobs for project: ".($project->title ?: "Project #{$project->id}"),
                $this->generation,
                $project->user,
                [
                    'project_id' => $project->id,
                    'generation_id' => $this->generation->id,
                    'current_stage' => $this->generation->current_stage,
                    'total_chapters' => $totalChapters,
                ]
            );
            }

        } catch (\Throwable $e) {
            Log::error('Bulk generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (config('activity.bulk_jobs', true)) {
            ActivityLog::record(
                'ai.bulk_generation.failed',
                "Bulk generation failed for project: ".($project->title ?: "Project #{$project->id}"),
                $this->generation,
                $project->user,
                [
                    'project_id' => $project->id,
                    'generation_id' => $this->generation->id,
                    'current_stage' => $this->generation->current_stage,
                    'error' => $e->getMessage(),
                ]
            );
            }

            $broadcaster->failed(
                $this->generation,
                $e->getMessage(),
                $this->generation->current_stage
            );

            $this->fail($e);
        }
    }

    private function runLiteratureMining(
        Project $project,
        PaperCollectionService $paperCollectionService,
        GenerationBroadcaster $broadcaster
    ): void {
        // Check if parallel execution is enabled
        if (config('ai.parallel_literature_mining', true)) {
            $this->runParallelLiteratureMining($project, $paperCollectionService, $broadcaster);
        } else {
            $this->runSequentialLiteratureMining($project, $paperCollectionService, $broadcaster);
        }
    }

    /**
     * Run literature mining with parallel API calls for better performance.
     */
    private function runParallelLiteratureMining(
        Project $project,
        PaperCollectionService $paperCollectionService,
        GenerationBroadcaster $broadcaster
    ): void {
        $isMedical = $this->isMedicalField($project->field_of_study);

        // Broadcast all "connecting" states
        $sources = ['semantic_scholar', 'openalex', 'arxiv', 'crossref'];
        if ($isMedical) {
            $sources[] = 'pubmed';
        }

        $broadcaster->literatureMining(
            $this->generation,
            'all',
            0,
            0,
            2,
            'Searching multiple academic databases in parallel...',
            'connecting'
        );

        // Execute all API calls in parallel using concurrent execution
        $papers = [];

        // Use parallel execution for independent API calls
        $results = $this->executeInParallel([
            'semantic' => fn () => $paperCollectionService->collectFromSemanticScholar($project->topic),
            'openalex' => fn () => $paperCollectionService->collectFromOpenAlex($project->topic),
            'arxiv' => fn () => $paperCollectionService->collectFromArXiv($project->topic),
            'crossref' => fn () => $paperCollectionService->collectFromCrossRef($project->topic),
            'pubmed' => $isMedical ? fn () => $paperCollectionService->collectFromPubMed($project->topic) : null,
        ]);

        // Process results and broadcast individual completions
        $totalPapers = 0;
        $progress = 5;

        foreach (['semantic', 'openalex', 'arxiv', 'pubmed', 'crossref'] as $source) {
            if (! isset($results[$source])) {
                continue;
            }

            $collection = $results[$source];
            $count = $collection->count();
            $papers[] = $collection;
            $totalPapers += $count;

            $sourceMap = [
                'semantic' => 'semantic_scholar',
                'openalex' => 'openalex',
                'arxiv' => 'arxiv',
                'pubmed' => 'pubmed',
                'crossref' => 'crossref',
            ];

            $sourceDisplay = match ($source) {
                'openalex' => 'OpenAlex',
                'arxiv' => 'arXiv',
                'pubmed' => 'PubMed',
                'crossref' => 'CrossRef',
                default => ucfirst($source),
            };

            $broadcaster->literatureMining(
                $this->generation,
                $sourceMap[$source],
                $count,
                $totalPapers,
                $progress,
                "Found {$count} papers from {$sourceDisplay}",
                'completed'
            );

            $progress += 3;
        }

        // Merge all papers
        $allPapers = collect($papers)->flatten(1);

        // Continue with deduplication
        $this->finalizeLiteratureMining($allPapers, $paperCollectionService, $broadcaster);
    }

    /**
     * Run literature mining sequentially (fallback/legacy mode).
     */
    private function runSequentialLiteratureMining(
        Project $project,
        PaperCollectionService $paperCollectionService,
        GenerationBroadcaster $broadcaster
    ): void {
        $totalPapers = 0;

        // Step 1: Semantic Scholar
        $broadcaster->literatureMining(
            $this->generation,
            'semantic_scholar',
            0,
            $totalPapers,
            2,
            'Searching Semantic Scholar...',
            'connecting'
        );

        $semanticPapers = $paperCollectionService->collectFromSemanticScholar($project->topic);
        $totalPapers += $semanticPapers->count();

        $broadcaster->literatureMining(
            $this->generation,
            'semantic_scholar',
            $semanticPapers->count(),
            $totalPapers,
            5,
            "Found {$semanticPapers->count()} papers from Semantic Scholar",
            'completed'
        );

        // Step 2: OpenAlex
        $broadcaster->literatureMining(
            $this->generation,
            'openalex',
            0,
            $totalPapers,
            7,
            'Searching OpenAlex...',
            'connecting'
        );

        $openAlexPapers = $paperCollectionService->collectFromOpenAlex($project->topic);
        $totalPapers += $openAlexPapers->count();

        $broadcaster->literatureMining(
            $this->generation,
            'openalex',
            $openAlexPapers->count(),
            $totalPapers,
            10,
            "Found {$openAlexPapers->count()} papers from OpenAlex",
            'completed'
        );

        // Step 3: arXiv (free preprints / PDFs)
        $broadcaster->literatureMining(
            $this->generation,
            'arxiv',
            0,
            $totalPapers,
            12,
            'Searching arXiv...',
            'connecting'
        );

        $arxivPapers = $paperCollectionService->collectFromArXiv($project->topic);
        $totalPapers += $arxivPapers->count();

        $broadcaster->literatureMining(
            $this->generation,
            'arxiv',
            $arxivPapers->count(),
            $totalPapers,
            15,
            "Found {$arxivPapers->count()} papers from arXiv",
            'completed'
        );

        // Step 4: PubMed (if medical field)
        $allPapers = $semanticPapers->merge($openAlexPapers)->merge($arxivPapers);

        if ($this->isMedicalField($project->field_of_study)) {
            $broadcaster->literatureMining(
                $this->generation,
                'pubmed',
                0,
                $totalPapers,
                16,
                'Medical field detected - searching PubMed...',
                'connecting'
            );

            $pubMedPapers = $paperCollectionService->collectFromPubMed($project->topic);
            $allPapers = $allPapers->merge($pubMedPapers);
            $totalPapers += $pubMedPapers->count();

            $broadcaster->literatureMining(
                $this->generation,
                'pubmed',
                $pubMedPapers->count(),
                $totalPapers,
                18,
                "Found {$pubMedPapers->count()} papers from PubMed",
                'completed'
            );
        }

        // Step 5: CrossRef
        $broadcaster->literatureMining(
            $this->generation,
            'crossref',
            0,
            $totalPapers,
            19,
            'Searching CrossRef...',
            'connecting'
        );

        $crossRefPapers = $paperCollectionService->collectFromCrossRef($project->topic);
        $allPapers = $allPapers->merge($crossRefPapers);
        $totalPapers += $crossRefPapers->count();

        $broadcaster->literatureMining(
            $this->generation,
            'crossref',
            $crossRefPapers->count(),
            $totalPapers,
            20,
            "Found {$crossRefPapers->count()} papers from CrossRef",
            'completed'
        );

        // Continue with deduplication
        $this->finalizeLiteratureMining($allPapers, $paperCollectionService, $broadcaster);
    }

    /**
     * Execute multiple callables in parallel.
     */
    private function executeInParallel(array $tasks): array
    {
        $results = [];

        // Filter out null tasks
        $tasks = array_filter($tasks, fn ($task) => $task !== null);

        // For now, execute sequentially as a fallback
        // In production, you could use:
        // - Laravel's Process::pool() for true parallel execution
        // - Swoole/ReactPHP for async execution
        // - Spatie's Fork package for process forking
        foreach ($tasks as $key => $task) {
            try {
                $results[$key] = $task();
            } catch (\Throwable $e) {
                Log::warning("Paper collection failed for {$key}: {$e->getMessage()}");
                $results[$key] = collect();
            }
        }

        return $results;
    }

    /**
     * Finalize literature mining with deduplication and storage.
     */
    private function finalizeLiteratureMining(
        $allPapers,
        PaperCollectionService $paperCollectionService,
        GenerationBroadcaster $broadcaster
    ): void {
        $totalPapers = $allPapers->count();

        // Deduplicate and rank
        $broadcaster->literatureMining(
            $this->generation,
            'processing',
            0,
            $totalPapers,
            18,
            'Deduplicating and ranking papers...',
            'deduplicating'
        );

        $finalPapers = $paperCollectionService->deduplicateAndRank($allPapers);

        // Store papers
        $broadcaster->literatureMining(
            $this->generation,
            'storing',
            $finalPapers->count(),
            $finalPapers->count(),
            19,
            'Storing papers in database...',
            'storing'
        );

        $paperCollectionService->storePapersForProject($this->generation->project, $finalPapers);

        // Final literature mining complete
        $broadcaster->literatureMining(
            $this->generation,
            'complete',
            $finalPapers->count(),
            $finalPapers->count(),
            20,
            "Literature mining completed - {$finalPapers->count()} papers collected",
            'completed'
        );
    }

    private function startChapterGeneration(
        Project $project,
        array $chapterStructure,
        GenerationBroadcaster $broadcaster
    ): void {
        $startChapter = 1;
        $allChaptersComplete = false;

        if ($this->resume) {
            $firstIncomplete = $project->chapters()
                ->where('status', '!=', 'completed')
                ->orderBy('chapter_number')
                ->first();

            if ($firstIncomplete) {
                $startChapter = $firstIncomplete->chapter_number;
                Log::info("Resuming: Starting from chapter {$startChapter}", [
                    'project_id' => $project->id,
                ]);
            } else {
                if ($project->chapters()->count() > 0) {
                    $allChaptersComplete = true;
                    Log::info('Resuming: All chapters completed', [
                        'project_id' => $project->id,
                    ]);
                }
            }
        }

        if ($allChaptersComplete) {
            // Skip directly to HTML conversion
            dispatch(new ConvertChaptersToHtml($project, $this->generation));
        } else {
            // Dispatch first chapter generation
            // The GenerateChapter job will chain to the next chapter
            dispatch(new GenerateChapter(
                $project,
                $startChapter,
                $this->generation,
                $chapterStructure
            ));
        }
    }

    private function isMedicalField(?string $field): bool
    {
        if (! $field) {
            return false;
        }

        $medicalFields = [
            'medicine', 'health', 'biology', 'biochemistry', 'pharmacology',
            'nursing', 'public health', 'epidemiology', 'medical',
        ];

        return collect($medicalFields)->contains(function ($medField) use ($field) {
            return stripos($field, $medField) !== false;
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $broadcaster = app(GenerationBroadcaster::class);

        $broadcaster->failed(
            $this->generation,
            $exception->getMessage(),
            $this->generation->current_stage
        );
    }
}
