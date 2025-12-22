<?php

namespace App\Jobs;

use App\Models\DefenseSlideDeck;
use App\Services\AI\ClaudeSkillsService;
use App\Services\Defense\DefenseCreditService;
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

    public function handle(ClaudeSkillsService $skillsService, DefenseCreditService $creditService): void
    {
        $deck = DefenseSlideDeck::with('project', 'user')->find($this->deckId);
        if (! $deck || ! $deck->project || ! $deck->user) {
            return;
        }

        if (! $creditService->hasEnoughCredits($deck->user, 'text')) {
            $deck->update([
                'status' => 'failed',
                'error_message' => 'Insufficient credit balance for PPTX rendering.',
            ]);
            return;
        }

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

            $result = $skillsService->generatePptx($slides, $deck->project->title, $filename);
            $fileContent = $skillsService->downloadFile($result['file_id']);

            $storagePath = "defense-slides/{$deck->project_id}/{$filename}";
            Storage::disk('public')->put($storagePath, $fileContent);

            $deck->update([
                'pptx_path' => $storagePath,
                'status' => 'ready',
                'ai_models' => array_merge($deck->ai_models ?? [], [
                    'pptx' => 'claude-skills-pptx',
                ]),
            ]);

            $creditService->deductForTextExchange(
                $deck->user,
                null,
                json_encode($slides, JSON_UNESCAPED_SLASHES),
                'Defense deck PPTX rendering'
            );
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
