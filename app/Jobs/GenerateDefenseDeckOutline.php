<?php

namespace App\Jobs;

use App\Models\DefenseSlideDeck;
use App\Services\AIContentGenerator;
use App\Services\Defense\DefenseContentExtractor;
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

    /**
     * The number of seconds the job can run before timing out.
     * Two-pass generation requires multiple AI calls per chapter.
     */
    public int $timeout = 300;

    public function __construct(private int $deckId) {}

    public function handle(
        AIContentGenerator $aiGenerator,
        DefenseSlideDeckService $deckService,
        DefenseContentExtractor $contentExtractor,
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
            'status' => 'extracting',
            'extraction_status' => 'extracting',
        ]);

        try {
            $prompt = null;
            $extractedData = null;
            $usedExtraction = false;

            try {
                $extractedData = $contentExtractor->extractFromProject($deck->project);
                $deck->update([
                    'extraction_data' => $extractedData,
                    'extraction_status' => 'extracted',
                ]);
                $prompt = $deckService->buildSlidePromptFromExtraction($deck->project, $extractedData);
                $usedExtraction = true;
            } catch (\Throwable $e) {
                Log::warning('Defense deck extraction failed, falling back to legacy prompt', [
                    'deck_id' => $deck->id,
                    'project_id' => $deck->project_id,
                    'error' => $e->getMessage(),
                ]);
                $deck->update([
                    'extraction_status' => 'failed',
                ]);
                $prompt = $deckService->buildSlidePrompt($deck->project);
            }

            $deck->update([
                'status' => 'generating',
            ]);

            $raw = $aiGenerator->generate($prompt, [
                'feature' => 'defense_slide_outline',
                'model' => 'gpt-4o',
                'temperature' => 0.4,
                'max_tokens' => 16000,
                'user_id' => $deck->user_id,
            ]);

            $payload = $deckService->extractSlidesPayload($raw);
            $slides = $payload ? $deckService->normalizeSlides($payload, 30) : [];
            $wysiwygSlides = $slides ? $deckService->toWysiwygSlides($slides) : [];

            if (empty($wysiwygSlides)) {
                throw new \RuntimeException('Failed to parse slide outline JSON.');
            }

            $deck->update([
                'slides_json' => $wysiwygSlides,
                'is_wysiwyg' => true,
                'editor_version' => '1.0.0',
                'status' => 'outlined',
                'ai_models' => array_merge($deck->ai_models ?? [], [
                    'outline' => 'gpt-4o',
                ], $usedExtraction ? [
                    'extraction' => 'gpt-4o-mini',
                ] : []),
            ]);

            $creditService->deductForTextExchange(
                $deck->user,
                null,
                json_encode($slides, JSON_UNESCAPED_SLASHES),
                'Defense slide outline'
            );

        } catch (\Throwable $e) {
            Log::error('Defense deck outline generation failed', [
                'deck_id' => $deck->id,
                'project_id' => $deck->project_id,
                'error' => $e->getMessage(),
            ]);
            $deck->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'extraction_status' => $deck->extraction_status === 'extracting' ? 'failed' : $deck->extraction_status,
            ]);
        }
    }
}
