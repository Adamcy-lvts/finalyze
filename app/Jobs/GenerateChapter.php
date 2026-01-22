<?php

namespace App\Jobs;

use App\Http\Controllers\ChapterController;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\ProjectGeneration;
use App\Services\GenerationBroadcaster;
use App\Jobs\Concerns\CancellationAware;
use App\Jobs\Concerns\GenerationCancelledException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateChapter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CancellationAware;

    public $timeout = 1200; // 20 minutes per chapter

    public function __construct(
        public Project $project,
        public int $chapterNumber,
        public ProjectGeneration $generation,
        public array $chapterStructure
    ) {}

    public function handle(GenerationBroadcaster $broadcaster): void
    {
        $startTime = microtime(true);
        $totalChapters = count($this->chapterStructure);
        $chapterTitle = $this->getChapterTitle();
        $chapterController = app(ChapterController::class);
        $targetWordCount = $chapterController->getChapterWordCount($this->project, $this->chapterNumber);

        try {
            $this->checkCancellation($this->generation);

            Log::info("Starting generation for Chapter {$this->chapterNumber}", [
                'project_id' => $this->project->id,
                'chapter_number' => $this->chapterNumber,
            ]);

            // Broadcast chapter started
            $broadcaster->chapterStarted(
                $this->generation,
                $this->chapterNumber,
                $chapterTitle,
                $targetWordCount,
                $totalChapters
            );

            // Phase 1: Building prompt (0-20%)
            $broadcaster->chapterProgress(
                $this->generation,
                $this->chapterNumber,
                10,
                0,
                $targetWordCount,
                $totalChapters,
                "Building prompt for Chapter {$this->chapterNumber}..."
            );

            $prompt = $chapterController->buildProgressivePrompt($this->project, $this->chapterNumber);

            $this->checkCancellation($this->generation);

            // Phase 2: Generating content (20-80%)
            $broadcaster->chapterProgress(
                $this->generation,
                $this->chapterNumber,
                20,
                0,
                $targetWordCount,
                $totalChapters,
                "Generating content for Chapter {$this->chapterNumber}... (Target: {$targetWordCount} words)"
            );

            // Generate with progress updates
            $content = $this->generateWithProgressUpdates(
                $chapterController,
                $prompt,
                $targetWordCount,
                $broadcaster,
                $totalChapters
            );

            // Phase 3: Saving (80-100%)
            $broadcaster->chapterProgress(
                $this->generation,
                $this->chapterNumber,
                85,
                str_word_count(strip_tags($content)),
                $targetWordCount,
                $totalChapters,
                "Processing and saving Chapter {$this->chapterNumber}..."
            );

            // Save chapter in a transaction
            $chapter = DB::transaction(function () use ($content, $chapterTitle, $targetWordCount) {
                return Chapter::updateOrCreate([
                    'project_id' => $this->project->id,
                    'chapter_number' => $this->chapterNumber,
                ], [
                    'title' => $chapterTitle,
                    'content' => $content,
                    'word_count' => str_word_count(strip_tags($content)),
                    'target_word_count' => $targetWordCount,
                    'status' => 'completed',
                    'ai_generated' => true,
                    'last_ai_generation' => now(),
                ]);
            });

            $generationTime = round(microtime(true) - $startTime, 2);
            $chaptersCompleted = $this->project->chapters()
                ->where('status', 'completed')
                ->count();

            // Broadcast chapter completed
            $broadcaster->chapterCompleted(
                $this->generation,
                $this->chapterNumber,
                $chapterTitle,
                $chapter->word_count,
                $targetWordCount,
                $generationTime,
                $chaptersCompleted,
                $totalChapters
            );

            Log::info("Chapter {$this->chapterNumber} generated successfully", [
                'word_count' => $chapter->word_count,
                'generation_time' => $generationTime,
            ]);

            $this->checkCancellation($this->generation);

            // Dispatch next chapter or finalization
            $this->dispatchNextStep($broadcaster, $totalChapters);

        } catch (GenerationCancelledException $e) {
            $this->handleCancellation($this->generation);
            return;
        } catch (\Throwable $e) {
            Log::error("Chapter {$this->chapterNumber} generation failed: ".$e->getMessage());

            $broadcaster->failed(
                $this->generation,
                $e->getMessage(),
                "chapter_generation_{$this->chapterNumber}",
                $this->chapterNumber
            );

            $this->fail($e);
        }
    }

    /**
     * Generate content with AI service and broadcast real-time progress via WebSocket.
     *
     * Uses streaming AI generation to get actual word counts and broadcasts
     * progress updates every ~150 words for real-time UI feedback.
     */
    private function generateWithProgressUpdates(
        ChapterController $controller,
        string $prompt,
        int $targetWordCount,
        GenerationBroadcaster $broadcaster,
        int $totalChapters
    ): string {
        $lastBroadcastTime = microtime(true);
        $minBroadcastInterval = 1.0; // Minimum 1 second between broadcasts to avoid flooding

        // Progress callback that broadcasts via WebSocket
        $onProgress = function (int $wordCount, int $chapterProgress, string $description) use (
            $broadcaster,
            $targetWordCount,
            $totalChapters,
            &$lastBroadcastTime,
            $minBroadcastInterval
        ) {
            $this->checkCancellation($this->generation);
            $now = microtime(true);

            // Throttle broadcasts to avoid overwhelming the WebSocket
            if (($now - $lastBroadcastTime) < $minBroadcastInterval) {
                return;
            }

            $broadcaster->chapterProgress(
                $this->generation,
                $this->chapterNumber,
                $chapterProgress,
                $wordCount,
                $targetWordCount,
                $totalChapters,
                $description
            );

            $lastBroadcastTime = $now;
        };

        // Use the new streaming method with progress callback
        return $controller->generateWithRealtimeProgress(
            $prompt,
            $targetWordCount,
            $onProgress,
            150 // Report progress every ~150 words
        );
    }

    /**
     * Dispatch the next chapter or finalization job
     */
    private function dispatchNextStep(GenerationBroadcaster $broadcaster, int $totalChapters): void
    {
        $this->checkCancellation($this->generation);

        $nextChapterNumber = $this->chapterNumber + 1;

        if ($nextChapterNumber <= $totalChapters) {
            Log::info("Dispatching next chapter: {$nextChapterNumber}");

            dispatch(new GenerateChapter(
                $this->project,
                $nextChapterNumber,
                $this->generation,
                $this->chapterStructure
            ));
        } else {
            Log::info('All chapters generated. Starting HTML conversion.');

            // Broadcast that we're moving to HTML conversion
            $broadcaster->htmlConversion(
                $this->generation,
                'Starting final formatting...',
                95
            );

            dispatch(new ConvertChaptersToHtml(
                $this->project,
                $this->generation
            ));
        }
    }

    private function getChapterTitle(): string
    {
        foreach ($this->chapterStructure as $chapter) {
            $chapterNum = $chapter['number'] ?? $chapter['chapter_number'] ?? null;

            if ($chapterNum == $this->chapterNumber) {
                return $chapter['title'] ?? $chapter['chapter_title'] ?? "Chapter {$this->chapterNumber}";
            }
        }

        return "Chapter {$this->chapterNumber}";
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
            "chapter_generation_{$this->chapterNumber}",
            $this->chapterNumber
        );
    }
}
