<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectGeneration;
use App\Services\GenerationBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConvertChaptersToHtml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes

    public function __construct(
        public Project $project,
        public ProjectGeneration $generation
    ) {}

    public function handle(GenerationBroadcaster $broadcaster): void
    {
        $startTime = microtime(true);

        try {
            Log::info("Starting HTML conversion for project {$this->project->id}");

            $broadcaster->htmlConversion(
                $this->generation,
                'Converting chapters to HTML format...',
                96
            );

            $chapters = $this->project->chapters()->orderBy('chapter_number')->get();
            $totalWordCount = 0;

            foreach ($chapters as $index => $chapter) {
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

            // Broadcast completion
            $broadcaster->completed(
                $this->generation,
                $totalWordCount,
                $chapters->count(),
                $totalDuration,
                $papersCollected,
                $this->getDownloadLinks()
            );

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
            'docx' => route('projects.export.docx', $this->project->slug),
            'pdf' => route('projects.export.pdf', $this->project->slug),
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
}
