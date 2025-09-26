<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProjectOutlineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateProjectOutline implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Project $project
    ) {
        // Set queue name for better management
        $this->onQueue('outline-generation');
    }

    /**
     * Execute the job.
     */
    public function handle(ProjectOutlineService $outlineService): void
    {
        Log::info('QUEUE - Starting outline generation job', [
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'attempt' => $this->attempts(),
        ]);

        try {
            $success = $outlineService->generateProjectOutline($this->project);

            if ($success) {
                Log::info('QUEUE - Outline generation completed successfully', [
                    'project_id' => $this->project->id,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                Log::error('QUEUE - Outline generation failed', [
                    'project_id' => $this->project->id,
                    'attempt' => $this->attempts(),
                ]);

                // Fail the job to trigger retry mechanism
                $this->fail(new \Exception('Outline generation returned false'));
            }

        } catch (\Exception $e) {
            Log::error('QUEUE - Outline generation job failed with exception', [
                'project_id' => $this->project->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('QUEUE - Outline generation job failed permanently', [
            'project_id' => $this->project->id,
            'total_attempts' => $this->tries,
            'error' => $exception->getMessage(),
        ]);

        // Could optionally notify user or set a flag on the project
        // that outline generation failed and needs manual intervention
    }
}
