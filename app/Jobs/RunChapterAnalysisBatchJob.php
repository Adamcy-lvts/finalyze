<?php

namespace App\Jobs;

use App\Models\ChapterAnalysisBatch;
use App\Services\ChapterAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunChapterAnalysisBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;

    public function __construct(private ChapterAnalysisBatch $batch)
    {
        //
    }

    public function handle(ChapterAnalysisService $analysisService): void
    {
        $batch = $this->batch->fresh(['items.chapter']);
        if (! $batch) {
            return;
        }

        $consumedWords = 0;

        $batch->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        foreach ($batch->items as $item) {
            try {
                $item->update([
                    'status' => 'running',
                    'started_at' => now(),
                ]);

                $result = $analysisService->analyzeChapter($item->chapter);

                $item->update([
                    'status' => 'completed',
                    'analysis_result_id' => $result->id,
                    'completed_at' => now(),
                ]);

                $consumedWords += $this->estimateOutputWords($result);
                $batch->increment('completed_chapters');
            } catch (\Exception $e) {
                Log::error('Chapter analysis failed in batch', [
                    'batch_id' => $batch->id,
                    'chapter_id' => $item->chapter_id,
                    'error' => $e->getMessage(),
                ]);

                $item->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);

                $batch->increment('failed_chapters');
            }
        }

        $batch->update([
            'status' => $batch->failed_chapters > 0 && $batch->completed_chapters === 0 ? 'failed' : 'completed',
            'completed_at' => now(),
            'consumed_words' => $consumedWords,
        ]);
    }

    /**
     * Roughly estimate the words produced by the AI analysis output.
     * We aggregate text from feedback/analysis/suggestions arrays that were persisted on the result.
     */
    private function estimateOutputWords($result): int
    {
        $sources = [
            $result->structure_feedback,
            $result->citation_analysis,
            $result->suggestions,
            $result->readability_metrics,
            $result->grammar_issues,
        ];

        $countWords = function ($value) use (&$countWords): int {
            if (is_null($value)) {
                return 0;
            }

            if (is_string($value)) {
                return str_word_count(strip_tags($value));
            }

            if (is_array($value)) {
                return array_reduce($value, fn ($carry, $v) => $carry + $countWords($v), 0);
            }

            return 0;
        };

        return array_reduce($sources, fn ($carry, $source) => $carry + $countWords($source), 0);
    }
}
