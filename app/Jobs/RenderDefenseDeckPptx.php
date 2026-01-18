<?php

namespace App\Jobs;

use App\Models\DefenseSlideDeck;
use App\Services\Defense\DefenseCreditService;
use App\Services\Defense\PptxGenJsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenderDefenseDeckPptx implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(private int $deckId) {}

    public function handle(
        DefenseCreditService $creditService,
        PptxGenJsService $pptxGenJsService
    ): void {
        $deck = DefenseSlideDeck::with('project', 'user')->find($this->deckId);
        if (! $deck || ! $deck->project || ! $deck->user) {
            Log::warning('Defense deck PPTX render aborted: missing deck/project/user', [
                'deck_id' => $this->deckId,
            ]);

            return;
        }

        Log::info('Defense deck PPTX render started', [
            'deck_id' => $deck->id,
            'project_id' => $deck->project_id,
            'status' => $deck->status,
        ]);

        $deck->update([
            'status' => 'rendering',
        ]);

        try {
            $slides = $deck->slides_json ?? [];
            if (empty($slides)) {
                throw new \RuntimeException('Slide outline not available.');
            }

            $safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '-', strtolower((string) $deck->project->title));
            $safeTitle = trim($safeTitle, '-') ?: 'defense-deck';
            $filename = "{$safeTitle}-{$deck->id}.pptx";
            $storagePath = "defense-slides/{$deck->project_id}/{$filename}";

            $isWysiwyg = $deck->is_wysiwyg ?? false;
            $tempPath = $pptxGenJsService->export($slides, $deck->project->title, $filename, $isWysiwyg);
            $fileContent = file_get_contents($tempPath);

            Storage::disk('public')->put($storagePath, $fileContent);

            $deck->update([
                'pptx_path' => $storagePath,
                'status' => 'ready',
                'ai_models' => array_merge($deck->ai_models ?? [], [
                    'pptx' => 'pptxgenjs',
                ]),
            ]);
            Log::info('Defense deck PPTX render completed', [
                'deck_id' => $deck->id,
                'project_id' => $deck->project_id,
                'pptx_path' => $storagePath,
            ]);

        } catch (\Throwable $e) {
            Log::error('Defense deck PPTX rendering failed', [
                'deck_id' => $deck->id,
                'project_id' => $deck->project_id,
                'error' => $e->getMessage(),
            ]);
            $deck->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
