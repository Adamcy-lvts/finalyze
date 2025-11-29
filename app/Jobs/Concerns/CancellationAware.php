<?php

namespace App\Jobs\Concerns;

use App\Models\ProjectGeneration;
use App\Services\GenerationBroadcaster;
use Illuminate\Support\Facades\Cache;

/**
 * Trait for jobs that should be cancellable.
 *
 * Provides methods to check if the generation has been cancelled
 * and handle graceful shutdown.
 */
trait CancellationAware
{
    /**
     * Check if the generation has been cancelled.
     */
    protected function isCancelled(ProjectGeneration $generation): bool
    {
        // Check cache flag first (fastest)
        if (Cache::get("generation_cancelled_{$generation->id}")) {
            return true;
        }

        // Double-check database
        $generation->refresh();

        return $generation->status === 'cancelled';
    }

    /**
     * Throw an exception if the generation has been cancelled.
     * Call this at key points during job execution.
     */
    protected function checkCancellation(ProjectGeneration $generation): void
    {
        if ($this->isCancelled($generation)) {
            throw new GenerationCancelledException("Generation {$generation->id} was cancelled");
        }
    }

    /**
     * Handle graceful cancellation.
     * Updates the generation status and broadcasts the cancellation.
     */
    protected function handleCancellation(ProjectGeneration $generation): void
    {
        $broadcaster = app(GenerationBroadcaster::class);

        $generation->update([
            'status' => 'cancelled',
            'message' => 'Generation was cancelled',
        ]);

        // Broadcast a custom cancellation event if needed
        // For now, we'll just log it
        \Illuminate\Support\Facades\Log::info('Generation job detected cancellation', [
            'generation_id' => $generation->id,
            'progress' => $generation->progress,
        ]);
    }
}

/**
 * Exception thrown when a generation is cancelled.
 */
class GenerationCancelledException extends \Exception
{
    //
}
