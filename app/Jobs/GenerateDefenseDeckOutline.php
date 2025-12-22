<?php

namespace App\Jobs;

use App\Models\DefenseSlideDeck;
use App\Services\AIContentGenerator;
use App\Services\Defense\DefenseCreditService;
use App\Services\Defense\DefenseSlideDeckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDefenseDeckOutline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $deckId) {}

    public function handle(
        AIContentGenerator $aiGenerator,
        DefenseSlideDeckService $deckService,
        DefenseCreditService $creditService
    ): void {
        $deck = DefenseSlideDeck::with('project', 'user')->find($this->deckId);
        if (! $deck || ! $deck->project || ! $deck->user) {
            return;
        }

        if (! $creditService->hasEnoughCredits($deck->user, 'text')) {
            $deck->update([
                'status' => 'failed',
                'error_message' => 'Insufficient credit balance for defense deck generation.',
            ]);
            return;
        }

        $deck->update([
            'status' => 'outlining',
        ]);

        try {
            $prompt = $deckService->buildSlidePrompt($deck->project);
            $raw = $aiGenerator->generate($prompt, [
                'feature' => 'defense_slide_outline',
                'model' => 'gpt-4o',
                'temperature' => 0.25,
                'user_id' => $deck->user_id,
            ]);

            $payload = $deckService->extractSlidesPayload($raw);
            $slides = $payload ? $deckService->normalizeSlides($payload, 20) : [];

            if (empty($slides)) {
                throw new \RuntimeException('Failed to parse slide outline JSON.');
            }

            $deck->update([
                'slides_json' => $slides,
                'status' => 'outlined',
                'ai_models' => array_merge($deck->ai_models ?? [], [
                    'outline' => 'gpt-4o',
                ]),
            ]);

            $creditService->deductForTextExchange(
                $deck->user,
                null,
                json_encode($slides, JSON_UNESCAPED_SLASHES),
                'Defense slide outline'
            );

            RenderDefenseDeckPptx::dispatch($deck->id);
        } catch (\Throwable $e) {
            Log::error('Defense deck outline generation failed', [
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
