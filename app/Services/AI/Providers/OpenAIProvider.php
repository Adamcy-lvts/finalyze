<?php

namespace App\Services\AI\Providers;

use Generator;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderInterface
{
    private string $model = 'gpt-4o-mini';

    private float $temperature = 0.7;

    private int $maxTokens = 4000;

    public function generate(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

        Log::info('OpenAI Provider - Starting generation', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert academic writer specializing in thesis and dissertation writing. Write comprehensive, well-structured academic content with proper citations, formal academic language, and logical flow.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

            $content = $response->choices[0]->message->content;

            Log::info('OpenAI Provider - Generation completed', [
                'content_length' => strlen($content),
                'word_count' => str_word_count($content),
                'tokens_used' => $response->usage->totalTokens ?? 0,
            ]);

            return $content;

        } catch (\Exception $e) {
            Log::error('OpenAI Provider - Generation failed', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            throw $e;
        }
    }

    public function streamGenerate(string $prompt, array $options = []): Generator
    {
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

        Log::info('OpenAI Provider - Starting stream generation', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        try {
            $stream = OpenAI::chat()->createStreamed([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert academic writer specializing in thesis and dissertation writing. Write comprehensive, well-structured academic content with proper citations, formal academic language, and logical flow. Structure your writing with clear headings and subheadings.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stream' => true,
            ]);

            $totalChunks = 0;
            $totalContent = '';

            foreach ($stream as $response) {
                $content = $response->choices[0]->delta->content ?? '';

                if (! empty($content)) {
                    $totalChunks++;
                    $totalContent .= $content;

                    Log::debug('OpenAI Provider - Stream chunk', [
                        'chunk_number' => $totalChunks,
                        'chunk_length' => strlen($content),
                        'total_length' => strlen($totalContent),
                        'word_count' => str_word_count($totalContent),
                    ]);

                    yield $content;
                }

                // Check if we've reached the end
                if (isset($response->choices[0]->finish_reason) &&
                    $response->choices[0]->finish_reason === 'stop') {
                    break;
                }
            }

            Log::info('OpenAI Provider - Stream completed', [
                'total_chunks' => $totalChunks,
                'final_content_length' => strlen($totalContent),
                'final_word_count' => str_word_count($totalContent),
            ]);

        } catch (\Exception $e) {
            Log::error('OpenAI Provider - Stream generation failed', [
                'error' => $e->getMessage(),
                'model' => $model,
                'trace' => $e->getTraceAsString(),
            ]);

            // Yield error message to client
            yield 'Error generating content with OpenAI: '.$e->getMessage();
            throw $e;
        }
    }

    public function isAvailable(): bool
    {
        try {
            // Simple test to check if OpenAI is working
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
                'max_tokens' => 5,
            ]);

            Log::info('OpenAI Provider - Availability check passed');

            return true;

        } catch (\Exception $e) {
            Log::warning('OpenAI Provider - Availability check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    public function getCapabilities(): array
    {
        return [
            'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
            'max_tokens' => 4096,
            'supports_streaming' => true,
            'supports_system_prompt' => true,
            'languages' => ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'],
        ];
    }

    public function getCostPer1KTokens(): float
    {
        return 0.15; // $0.15 per 1K input tokens for gpt-4o-mini
    }
}
