<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectGeneration;
use App\Services\GenerationBroadcaster;
use App\Services\WordBalanceService;
use App\Jobs\Concerns\CancellationAware;
use App\Jobs\Concerns\GenerationCancelledException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ConvertChaptersToHtml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CancellationAware;

    public $timeout = 600; // 10 minutes

    public function __construct(
        public Project $project,
        public ProjectGeneration $generation
    ) {}

    public function handle(GenerationBroadcaster $broadcaster, WordBalanceService $wordBalanceService): void
    {
        $startTime = microtime(true);

        try {
            $this->checkCancellation($this->generation);

            Log::info("Starting HTML conversion for project {$this->project->id}");

            $broadcaster->htmlConversion(
                $this->generation,
                'Converting chapters to HTML format...',
                96
            );

            $chapters = $this->project->chapters()->orderBy('chapter_number')->get();
            $totalWordCount = 0;

            foreach ($chapters as $index => $chapter) {
                $this->checkCancellation($this->generation);

                if ($chapter->content) {
                    // Convert Markdown to HTML
                    $htmlContent = Str::markdown($chapter->content);
                    $chapter->update(['content' => $htmlContent]);
                    $totalWordCount += $chapter->word_count;

                    // Broadcast progress for each chapter converted
                    $conversionProgress = 96 + (($index + 1) / $chapters->count()) * 3;
                    $broadcaster->htmlConversion(
                        $this->generation,
                        "Formatting Chapter {$chapter->chapter_number}...",
                        (int) $conversionProgress
                    );
                }
            }

            Log::info("HTML conversion completed for project {$this->project->id}");

            $totalDuration = round(microtime(true) - $this->getGenerationStartTime(), 2);
            $papersCollected = $this->project->collectedPapers()->count();

            // Bill words used (only once per generation; uses delta to avoid double billing)
            $this->billWordUsage($totalWordCount, $wordBalanceService);

            // Broadcast completion
            $broadcaster->completed(
                $this->generation,
                $totalWordCount,
                $chapters->count(),
                $totalDuration,
                $papersCollected,
                $this->getDownloadLinks()
            );

        } catch (GenerationCancelledException $e) {
            $this->handleCancellation($this->generation);
            return;
        } catch (\Throwable $e) {
            Log::error('HTML conversion failed: '.$e->getMessage());

            $broadcaster->failed(
                $this->generation,
                $e->getMessage(),
                'html_conversion'
            );

            $this->fail($e);
        }
    }

    /**
     * Get the generation start time from metadata
     */
    private function getGenerationStartTime(): float
    {
        $metadata = $this->generation->metadata ?? [];

        if (isset($metadata['started_at'])) {
            return strtotime($metadata['started_at']);
        }

        // Fallback to generation created_at
        return $this->generation->created_at->timestamp;
    }

    /**
     * Generate download links for the completed project
     */
    private function getDownloadLinks(): array
    {
        return [
            'docx' => route('export.project.word', $this->project),
            'pdf' => route('export.project.pdf', $this->project),
        ];
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
            'html_conversion'
        );
    }

    /**
     * Deduct words from user balance when generation completes.
     * Bills only the delta not previously charged (metadata.words_billed).
     */
    private function billWordUsage(int $totalWordCount, WordBalanceService $wordBalanceService): void
    {
        $metadata = $this->generation->metadata ?? [];
        $alreadyBilled = $metadata['words_billed'] ?? 0;
        $wordsToBill = max(0, $totalWordCount - $alreadyBilled);

        if ($wordsToBill <= 0) {
            return;
        }

        try {
            $wordBalanceService->deductForGeneration(
                $this->project->user,
                $wordsToBill,
                sprintf(
                    'Bulk project generation (%s)',
                    $this->project->title ?? $this->project->topic ?? "Project {$this->project->id}"
                ),
                'bulk_project',
                $this->project->id,
                [
                    'generation_id' => $this->generation->id,
                    'words_billed' => $alreadyBilled,
                    'total_words' => $totalWordCount,
                ]
            );

            $metadata['words_billed'] = $alreadyBilled + $wordsToBill;
            $this->generation->update(['metadata' => $metadata]);

            Log::info('Word balance deducted for bulk generation completion', [
                'project_id' => $this->project->id,
                'generation_id' => $this->generation->id,
                'words_billed' => $wordsToBill,
                'total_billed' => $metadata['words_billed'],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to deduct word balance for bulk generation', [
                'project_id' => $this->project->id,
                'generation_id' => $this->generation->id,
                'words_to_bill' => $wordsToBill,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
