<?php

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * StreamingService - Handles robust SSE streaming with:
 * - Periodic auto-save during generation
 * - Connection monitoring and client disconnect detection
 * - Chunk checksums for integrity verification
 * - Heartbeat messages to keep connection alive
 * - Graceful error handling with partial content preservation
 */
class StreamingService
{
    private int $chunkIndex = 0;

    private string $generationId;

    private int $lastSavedWordCount = 0;

    private float $lastHeartbeat;

    private float $lastSave;

    private string $accumulatedContent = '';

    private bool $isActive = true;

    public function __construct(
        private Chapter $chapter,
        private int $saveWordInterval = 500,
        private int $saveTimeInterval = 30,
        private int $heartbeatInterval = 10
    ) {
        $this->generationId = Str::uuid()->toString();
        $this->lastHeartbeat = microtime(true);
        $this->lastSave = microtime(true);
    }

    /**
     * Get the generation ID for this streaming session
     */
    public function getGenerationId(): string
    {
        return $this->generationId;
    }

    /**
     * Initialize the streaming session
     */
    public function initialize(): void
    {
        // Mark generation as in progress
        $this->chapter->update([
            'generation_in_progress' => true,
            'generation_id' => $this->generationId,
            'generation_started_at' => now(),
            'generation_last_saved_words' => 0,
        ]);

        Log::info('StreamingService: Initialized', [
            'chapter_id' => $this->chapter->id,
            'generation_id' => $this->generationId,
        ]);
    }

    /**
     * Send a start message
     */
    public function sendStart(string $message = 'Initializing AI generation...'): void
    {
        $this->send([
            'type' => 'start',
            'message' => $message,
            'generation_id' => $this->generationId,
        ]);
    }

    /**
     * Send a content chunk
     */
    public function sendChunk(string $content): void
    {
        if (! $this->isActive) {
            return;
        }

        $this->chunkIndex++;
        $this->accumulatedContent .= $content;

        $wordCount = str_word_count($this->accumulatedContent);

        $data = [
            'type' => 'content',
            'content' => $content,
            'word_count' => $wordCount,
            'chunk_index' => $this->chunkIndex,
            'generation_id' => $this->generationId,
            'checksum' => $this->calculateChecksum($content),
        ];

        $this->send($data);

        // Check if we should auto-save
        $this->maybeAutoSave($wordCount);

        // Check if we should send heartbeat
        $this->maybeHeartbeat();
    }

    /**
     * Send a heartbeat message to keep connection alive
     */
    public function sendHeartbeat(): void
    {
        $this->send([
            'type' => 'heartbeat',
            'timestamp' => now()->timestamp,
            'generation_id' => $this->generationId,
            'last_saved_words' => $this->lastSavedWordCount,
            'current_words' => str_word_count($this->accumulatedContent),
        ]);

        $this->lastHeartbeat = microtime(true);
    }

    /**
     * Send an auto-save notification
     */
    public function sendAutoSave(int $wordCount): void
    {
        $this->send([
            'type' => 'autosave',
            'word_count' => $wordCount,
            'generation_id' => $this->generationId,
        ]);
    }

    /**
     * Send completion message
     */
    public function sendComplete(?int $finalWordCount = null): void
    {
        $wordCount = $finalWordCount ?? str_word_count($this->accumulatedContent);

        // Final save
        $this->saveProgress($wordCount, true);

        $this->send([
            'type' => 'complete',
            'message' => 'Generation complete!',
            'final_word_count' => $wordCount,
            'content_updated' => true,
            'generation_id' => $this->generationId,
        ]);

        $this->isActive = false;
    }

    /**
     * Send error message with partial content preservation info
     */
    public function sendError(string $message, string $code = 'GENERATION_ERROR', bool $canResume = false): void
    {
        $wordCount = str_word_count($this->accumulatedContent);
        $partialSaved = $wordCount > 100;

        // Try to save partial content
        if ($partialSaved) {
            $this->saveProgress($wordCount, false);
        }

        $this->send([
            'type' => 'error',
            'message' => $message,
            'code' => $code,
            'partial_saved' => $partialSaved,
            'saved_word_count' => $partialSaved ? $wordCount : 0,
            'can_resume' => $canResume && $partialSaved,
            'generation_id' => $this->generationId,
        ]);

        $this->isActive = false;
    }

    /**
     * Send end-of-stream marker
     */
    public function sendEnd(): void
    {
        $this->send(['type' => 'end']);
        $this->isActive = false;
    }

    /**
     * Check if we should send a heartbeat
     */
    private function maybeHeartbeat(): void
    {
        $elapsed = microtime(true) - $this->lastHeartbeat;

        if ($elapsed >= $this->heartbeatInterval) {
            $this->sendHeartbeat();
        }
    }

    /**
     * Check if we should auto-save
     */
    private function maybeAutoSave(int $wordCount): void
    {
        $currentTime = microtime(true);
        $wordsSinceSave = $wordCount - $this->lastSavedWordCount;
        $timeSinceSave = $currentTime - $this->lastSave;

        $shouldSave = ($wordsSinceSave >= $this->saveWordInterval) ||
                      ($timeSinceSave >= $this->saveTimeInterval && $wordsSinceSave > 50);

        if ($shouldSave) {
            $this->saveProgress($wordCount);
            $this->sendAutoSave($wordCount);
        }
    }

    /**
     * Save progress to database
     */
    private function saveProgress(int $wordCount, bool $isFinal = false): void
    {
        try {
            DB::transaction(function () use ($wordCount, $isFinal) {
                $updateData = [
                    'content' => $this->accumulatedContent,
                    'word_count' => $wordCount,
                    'status' => 'draft',
                    'generation_last_saved_words' => $wordCount,
                ];

                if ($isFinal) {
                    $updateData['generation_in_progress'] = false;
                    $updateData['generation_id'] = null;
                    $updateData['generation_started_at'] = null;
                    $updateData['ai_generated'] = true;
                    $updateData['last_ai_generation'] = now();
                }

                $this->chapter->update($updateData);
            });

            $this->lastSavedWordCount = $wordCount;
            $this->lastSave = microtime(true);

            Log::debug('StreamingService: Progress saved', [
                'chapter_id' => $this->chapter->id,
                'word_count' => $wordCount,
                'is_final' => $isFinal,
                'generation_id' => $this->generationId,
            ]);

        } catch (\Exception $e) {
            Log::error('StreamingService: Failed to save progress', [
                'error' => $e->getMessage(),
                'chapter_id' => $this->chapter->id,
                'generation_id' => $this->generationId,
            ]);
        }
    }

    /**
     * Calculate a short checksum for integrity verification
     */
    private function calculateChecksum(string $content): string
    {
        return substr(hash('xxh3', $content), 0, 8);
    }

    /**
     * Send an SSE message
     */
    private function send(array $data): void
    {
        if (! $this->isActive && $data['type'] !== 'end' && $data['type'] !== 'error' && $data['type'] !== 'complete') {
            return;
        }

        echo 'data: '.json_encode($data)."\n\n";

        // Flush output buffers
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        // Check if client disconnected
        if (connection_aborted()) {
            $this->handleDisconnection();
        }
    }

    /**
     * Handle client disconnection gracefully
     */
    private function handleDisconnection(): void
    {
        $this->isActive = false;
        $wordCount = str_word_count($this->accumulatedContent);

        Log::warning('StreamingService: Client disconnected', [
            'chapter_id' => $this->chapter->id,
            'generation_id' => $this->generationId,
            'words_generated' => $wordCount,
            'last_saved_words' => $this->lastSavedWordCount,
        ]);

        // Save any unsaved content
        if ($wordCount > $this->lastSavedWordCount + 50) {
            try {
                $this->chapter->update([
                    'content' => $this->accumulatedContent,
                    'word_count' => $wordCount,
                    'status' => 'draft',
                    'generation_in_progress' => false,
                    'generation_interrupted' => true,
                    'generation_resume_from' => $wordCount,
                ]);

                Log::info('StreamingService: Saved content on disconnect', [
                    'chapter_id' => $this->chapter->id,
                    'word_count' => $wordCount,
                ]);

            } catch (\Exception $e) {
                Log::error('StreamingService: Failed to save on disconnect', [
                    'error' => $e->getMessage(),
                    'chapter_id' => $this->chapter->id,
                ]);
            }
        }

        throw new \RuntimeException('Client disconnected during streaming');
    }

    /**
     * Get accumulated content
     */
    public function getAccumulatedContent(): string
    {
        return $this->accumulatedContent;
    }

    /**
     * Get current word count
     */
    public function getCurrentWordCount(): int
    {
        return str_word_count($this->accumulatedContent);
    }

    /**
     * Set initial content (for resume scenarios)
     */
    public function setInitialContent(string $content): void
    {
        $this->accumulatedContent = $content;
        $this->lastSavedWordCount = str_word_count($content);
    }

    /**
     * Check if streaming is still active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Clean up on error or completion
     */
    public function cleanup(): void
    {
        $this->isActive = false;

        // Ensure generation tracking is cleared
        try {
            if ($this->chapter->generation_in_progress) {
                $this->chapter->update([
                    'generation_in_progress' => false,
                    'generation_id' => null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('StreamingService: Cleanup failed', [
                'error' => $e->getMessage(),
                'chapter_id' => $this->chapter->id,
            ]);
        }
    }
}
