<?php

namespace App\Services;

use App\Events\Generation\ChapterGenerationCompleted;
use App\Events\Generation\ChapterGenerationProgress;
use App\Events\Generation\ChapterGenerationStarted;
use App\Events\Generation\GenerationCompleted;
use App\Events\Generation\GenerationFailed;
use App\Events\Generation\GenerationStarted;
use App\Events\Generation\LiteratureMiningProgress;
use App\Models\ProjectGeneration;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for broadcasting generation progress events.
 *
 * Centralizes all broadcasting logic and ensures consistent event payload structure.
 * Also handles database updates alongside broadcasts to keep everything in sync.
 */
class GenerationBroadcaster
{
    /**
     * Broadcast that generation has started
     */
    public function started(
        ProjectGeneration $generation,
        int $totalChapters,
        bool $isResume = false
    ): void {
        $generation->update([
            'status' => 'processing',
            'current_stage' => 'initializing',
            'progress' => $isResume ? $generation->progress : 0,
            'message' => $isResume ? 'Resuming generation...' : 'Starting generation...',
        ]);

        broadcast(new GenerationStarted($generation, [
            'total_chapters' => $totalChapters,
            'is_resume' => $isResume,
            'estimated_duration' => $this->estimateDuration($totalChapters),
        ]));

        Log::info('Generation started broadcast sent', [
            'generation_id' => $generation->id,
            'total_chapters' => $totalChapters,
            'is_resume' => $isResume,
        ]);
    }

    /**
     * Broadcast literature mining progress
     */
    public function literatureMining(
        ProjectGeneration $generation,
        string $source,
        int $papersFound,
        int $totalPapers,
        int $progress,
        string $message,
        ?string $subStage = null
    ): void {
        $details = $this->addDetail($generation, [
            'type' => 'mining',
            'message' => $message,
            'source' => $source,
        ]);

        $generation->update([
            'current_stage' => 'literature_mining',
            'progress' => $progress,
            'message' => $message,
            'details' => $details,
        ]);

        broadcast(new LiteratureMiningProgress($generation, [
            'source' => $source,
            'papers_found' => $papersFound,
            'total_papers' => $totalPapers,
            'sub_stage' => $subStage,
        ]));
    }

    /**
     * Broadcast that a chapter generation has started
     */
    public function chapterStarted(
        ProjectGeneration $generation,
        int $chapterNumber,
        string $chapterTitle,
        int $targetWordCount,
        int $totalChapters
    ): void {
        $progress = $this->calculateOverallProgress($chapterNumber, 0, $totalChapters);

        $details = $this->addDetail($generation, [
            'type' => 'chapter_started',
            'chapter' => $chapterNumber,
            'message' => "📝 Starting Chapter {$chapterNumber}: {$chapterTitle}",
        ]);

        $generation->update([
            'current_stage' => "chapter_generation_{$chapterNumber}",
            'progress' => $progress,
            'message' => "Generating Chapter {$chapterNumber}: {$chapterTitle}...",
            'details' => $details,
            'metadata' => array_merge($generation->metadata ?? [], [
                'current_chapter' => $chapterNumber,
                'total_chapters' => $totalChapters,
                'chapter_progress' => 0,
            ]),
        ]);

        broadcast(new ChapterGenerationStarted($generation, [
            'chapter_number' => $chapterNumber,
            'chapter_title' => $chapterTitle,
            'target_word_count' => $targetWordCount,
            'total_chapters' => $totalChapters,
        ]));

        Log::info('Chapter generation started broadcast sent', [
            'generation_id' => $generation->id,
            'chapter' => $chapterNumber,
        ]);
    }

    /**
     * Broadcast chapter generation progress (can be called frequently)
     */
    public function chapterProgress(
        ProjectGeneration $generation,
        int $chapterNumber,
        int $chapterProgress,
        int $currentWordCount,
        int $targetWordCount,
        int $totalChapters,
        string $stageDescription = 'Generating content...'
    ): void {
        $overallProgress = $this->calculateOverallProgress(
            $chapterNumber,
            $chapterProgress,
            $totalChapters
        );

        $generation->update([
            'progress' => $overallProgress,
            'message' => $stageDescription,
            'metadata' => array_merge($generation->metadata ?? [], [
                'current_chapter' => $chapterNumber,
                'chapter_progress' => $chapterProgress,
                'current_word_count' => $currentWordCount,
            ]),
        ]);

        broadcast(new ChapterGenerationProgress($generation, [
            'chapter_number' => $chapterNumber,
            'chapter_progress' => $chapterProgress,
            'current_word_count' => $currentWordCount,
            'target_word_count' => $targetWordCount,
            'stage_description' => $stageDescription,
        ]));
    }

    /**
     * Broadcast that a chapter has been completed
     */
    public function chapterCompleted(
        ProjectGeneration $generation,
        int $chapterNumber,
        string $chapterTitle,
        int $wordCount,
        int $targetWordCount,
        float $generationTime,
        int $chaptersCompleted,
        int $totalChapters
    ): void {
        $progress = $this->calculateOverallProgress($chapterNumber, 100, $totalChapters);

        $details = $this->addDetail($generation, [
            'type' => 'chapter_completed',
            'chapter' => $chapterNumber,
            'message' => "✅ Chapter {$chapterNumber} completed ({$wordCount} words in {$generationTime}s)",
            'generation_time' => $generationTime,
            'word_count' => $wordCount,
        ]);

        // Update chapter timings for ETA calculation
        $metadata = $generation->metadata ?? [];
        $chapterTimings = $metadata['chapter_timings'] ?? [];
        $chapterTimings[$chapterNumber] = $generationTime;
        $metadata['chapter_timings'] = $chapterTimings;

        $generation->update([
            'progress' => $progress,
            'message' => "Chapter {$chapterNumber} completed",
            'details' => $details,
            'metadata' => $metadata,
        ]);

        broadcast(new ChapterGenerationCompleted($generation, [
            'chapter_number' => $chapterNumber,
            'chapter_title' => $chapterTitle,
            'word_count' => $wordCount,
            'target_word_count' => $targetWordCount,
            'generation_time' => $generationTime,
            'chapters_completed' => $chaptersCompleted,
            'total_chapters' => $totalChapters,
        ]));

        Log::info('Chapter completed broadcast sent', [
            'generation_id' => $generation->id,
            'chapter' => $chapterNumber,
            'word_count' => $wordCount,
            'generation_time' => $generationTime,
        ]);
    }

    /**
     * Broadcast that generation has completed successfully
     */
    public function completed(
        ProjectGeneration $generation,
        int $totalWordCount,
        int $totalChapters,
        float $totalDuration,
        int $papersCollected,
        ?array $downloadLinks = null
    ): void {
        $details = $this->addDetail($generation, [
            'type' => 'success',
            'message' => '🎉 Project generation completed successfully!',
        ]);

        $generation->update([
            'status' => 'completed',
            'current_stage' => 'completed',
            'progress' => 100,
            'message' => 'Project generation completed successfully!',
            'details' => $details,
            'metadata' => array_merge($generation->metadata ?? [], [
                'total_word_count' => $totalWordCount,
                'total_duration' => $totalDuration,
                'completed_at' => now()->toISOString(),
            ]),
        ]);

        broadcast(new GenerationCompleted($generation, [
            'total_word_count' => $totalWordCount,
            'total_chapters' => $totalChapters,
            'total_duration' => $totalDuration,
            'papers_collected' => $papersCollected,
            'download_links' => $downloadLinks,
        ]));

        Log::info('Generation completed broadcast sent', [
            'generation_id' => $generation->id,
            'total_word_count' => $totalWordCount,
            'total_duration' => $totalDuration,
        ]);
    }

    /**
     * Broadcast that generation has failed
     */
    public function failed(
        ProjectGeneration $generation,
        string $errorMessage,
        ?string $failedStage = null,
        ?int $failedChapter = null
    ): void {
        $lastSuccessfulChapter = $this->getLastSuccessfulChapter($generation);
        $canResume = $generation->progress > 0;

        $details = $this->addDetail($generation, [
            'type' => 'error',
            'message' => "❌ Generation failed: {$errorMessage}",
            'failed_stage' => $failedStage ?? $generation->current_stage,
            'failed_chapter' => $failedChapter,
        ]);

        $generation->update([
            'status' => 'failed',
            'message' => "Failed: {$errorMessage}",
            'details' => $details,
        ]);

        broadcast(new GenerationFailed($generation, [
            'error_message' => $errorMessage,
            'failed_stage' => $failedStage ?? $generation->current_stage,
            'failed_chapter' => $failedChapter,
            'can_resume' => $canResume,
            'last_successful_chapter' => $lastSuccessfulChapter,
        ]));

        Log::error('Generation failed broadcast sent', [
            'generation_id' => $generation->id,
            'error' => $errorMessage,
            'failed_stage' => $failedStage,
        ]);
    }

    /**
     * Broadcast HTML conversion progress
     */
    public function htmlConversion(
        ProjectGeneration $generation,
        string $message,
        int $progress = 95
    ): void {
        $details = $this->addDetail($generation, [
            'type' => 'conversion',
            'message' => "🎨 {$message}",
        ]);

        $generation->update([
            'current_stage' => 'html_conversion',
            'progress' => $progress,
            'message' => $message,
            'details' => $details,
        ]);

        // Use base event for simple progress update
        broadcast(new ChapterGenerationProgress($generation, [
            'chapter_number' => 0, // 0 indicates non-chapter stage
            'chapter_progress' => $progress,
            'current_word_count' => 0,
            'target_word_count' => 0,
            'stage_description' => $message,
        ]));
    }

    /**
     * Calculate overall progress based on current chapter and chapter progress
     * Literature mining: 0-20%
     * Chapters: 20-95%
     * HTML conversion: 95-100%
     */
    private function calculateOverallProgress(
        int $chapterNumber,
        int $chapterProgress,
        int $totalChapters
    ): int {
        $progressPerChapter = 75 / max(1, $totalChapters);
        $chapterStartProgress = 20 + (($chapterNumber - 1) * $progressPerChapter);
        $chapterEndProgress = 20 + ($chapterNumber * $progressPerChapter);

        $progress = $chapterStartProgress +
            (($chapterEndProgress - $chapterStartProgress) * ($chapterProgress / 100));

        return (int) min($progress, 95);
    }

    /**
     * Add a detail entry to the generation log
     */
    private function addDetail(ProjectGeneration $generation, array $detail): array
    {
        $details = $generation->details ?? [];

        $details[] = [
            'timestamp' => now()->toISOString(),
            ...$detail,
        ];

        // Keep only last 100 entries
        if (count($details) > 100) {
            array_shift($details);
        }

        return $details;
    }

    /**
     * Get the last successfully completed chapter number
     */
    private function getLastSuccessfulChapter(ProjectGeneration $generation): ?int
    {
        $project = $generation->project;

        $lastCompleted = $project->chapters()
            ->where('status', 'completed')
            ->orderByDesc('chapter_number')
            ->first();

        return $lastCompleted?->chapter_number;
    }

    /**
     * Estimate total duration based on chapter count
     */
    private function estimateDuration(int $totalChapters): string
    {
        $minutesPerChapter = 3; // Average
        $literatureMiningMinutes = 2;
        $totalMinutes = ($totalChapters * $minutesPerChapter) + $literatureMiningMinutes;

        if ($totalMinutes < 10) {
            return "{$totalMinutes}-".($totalMinutes + 5).' minutes';
        }

        return round($totalMinutes / 5) * 5 .'-'.(round($totalMinutes / 5) * 5 + 10).' minutes';
    }
}
