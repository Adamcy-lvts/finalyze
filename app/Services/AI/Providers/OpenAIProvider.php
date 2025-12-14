<?php

namespace App\Services\AI\Providers;

use App\Models\ActivityLog;
use App\Services\AIUsageLogger;
use Generator;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderInterface
{
    private string $model = 'gpt-4o-mini';

    private float $temperature = 0.7;

    private int $maxTokens = 8000; // Increased for comprehensive chapter generation

    public function generate(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;
        $feature = $options['feature'] ?? null;
        $userId = $options['user_id'] ?? null;
        $startedAt = hrtime(true);

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
                        'content' => 'You are an expert academic writer specializing in thesis and dissertation writing. Use consistent markdown formatting: **bold** for emphasis, *italic* for emphasis, ## for headings, ### for subheadings, - for bullet points, 1. for numbered lists. Always use proper markdown syntax.',
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
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            Log::info('OpenAI Provider - Generation completed', [
                'content_length' => strlen($content),
                'word_count' => str_word_count($content),
                'tokens_used' => $response->usage->totalTokens ?? 0,
            ]);

            // Log usage if token counts are available
            if (isset($response->usage)) {
                app(AIUsageLogger::class)->log(
                    $userId,
                    $feature,
                    $model,
                    $response->usage->promptTokens ?? 0,
                    $response->usage->completionTokens ?? 0,
                    $response->id ?? null,
                    []
                );
            }

            if (config('activity.ai_provider_calls', true)) {
                ActivityLog::record(
                    'ai.call.openai',
                    'OpenAI generation completed',
                    null,
                    $userId ? (int) $userId : null,
                    array_filter([
                        'provider' => 'openai',
                        'feature' => $feature,
                        'model' => $model,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                        'duration_ms' => $durationMs,
                        'prompt_length' => strlen($prompt),
                        'content_length' => strlen($content),
                        'tokens' => isset($response->usage) ? [
                            'prompt' => $response->usage->promptTokens ?? 0,
                            'completion' => $response->usage->completionTokens ?? 0,
                            'total' => $response->usage->totalTokens ?? 0,
                        ] : null,
                        'openai_id' => $response->id ?? null,
                    ], fn ($v) => $v !== null)
                );
            }

            return $content;

        } catch (\Exception $e) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            Log::error('OpenAI Provider - Generation failed', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            if (config('activity.ai_provider_calls', true)) {
                ActivityLog::record(
                    'ai.call.openai_failed',
                    'OpenAI generation failed',
                    null,
                    $userId ? (int) $userId : null,
                    array_filter([
                        'provider' => 'openai',
                        'feature' => $feature,
                        'model' => $model,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                        'duration_ms' => $durationMs,
                        'prompt_length' => strlen($prompt),
                        'error' => $e->getMessage(),
                    ], fn ($v) => $v !== null)
                );
            }
            throw $e;
        }
    }

    public function streamGenerate(string $prompt, array $options = []): Generator
    {
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;
        $feature = $options['feature'] ?? null;
        $userId = $options['user_id'] ?? null;
        $startedAt = hrtime(true);

        // Retry configuration for stream connection errors
        $maxRetries = 3;
        $retryDelay = 2; // seconds, will increase exponentially

        Log::info('OpenAI Provider - Starting stream generation', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $stream = OpenAI::chat()->createStreamed([
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert academic writer specializing in thesis and dissertation writing. Use consistent markdown formatting: **bold** for emphasis, *italic* for emphasis, ## for headings, ### for subheadings, - for bullet points, 1. for numbered lists. Always use proper markdown syntax.',
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
                $promptTokens = 0;

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
                    if (isset($response->choices[0]->finish_reason)) {
                        $finishReason = $response->choices[0]->finish_reason;

                        if ($finishReason === 'length') {
                            Log::warning('OpenAI Provider - Stream stopped: hit max_tokens limit', [
                                'total_chunks' => $totalChunks,
                                'content_length' => strlen($totalContent),
                                'word_count' => str_word_count($totalContent),
                                'finish_reason' => 'length',
                            ]);
                        }

                        if ($finishReason === 'stop' || $finishReason === 'length') {
                            break;
                        }
                    }
                }

                Log::info('OpenAI Provider - Stream completed', [
                    'total_chunks' => $totalChunks,
                    'final_content_length' => strlen($totalContent),
                    'final_word_count' => str_word_count($totalContent),
                    'attempt' => $attempt,
                ]);

                // Streaming responses from OpenAI do not include token usage;
                // approximate using word count so we at least track something.
                $approxTokens = (int) round(str_word_count($totalContent) * 1.3);
                $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
                app(AIUsageLogger::class)->log(
                    $userId,
                    $feature,
                    $model,
                    $promptTokens,
                    $approxTokens,
                    null,
                    ['approx_stream_tokens' => true]
                );

                if (config('activity.ai_provider_calls', true)) {
                    ActivityLog::record(
                        'ai.call.openai_stream',
                        'OpenAI stream completed',
                        null,
                        $userId ? (int) $userId : null,
                        array_filter([
                            'provider' => 'openai',
                            'feature' => $feature,
                            'model' => $model,
                            'temperature' => $temperature,
                            'max_tokens' => $maxTokens,
                            'duration_ms' => $durationMs,
                            'prompt_length' => strlen($prompt),
                            'content_length' => strlen($totalContent),
                            'total_chunks' => $totalChunks,
                            'tokens' => [
                                'prompt' => $promptTokens,
                                'completion' => $approxTokens,
                                'approx_stream_tokens' => true,
                            ],
                        ], fn ($v) => $v !== null)
                    );
                }

                // Success - exit the retry loop
                return;

            } catch (\OpenAI\Exceptions\RateLimitException $e) {
                Log::warning('OpenAI Provider - Rate limit exceeded during streaming', [
                    'error' => $e->getMessage(),
                    'model' => $model,
                    'attempt' => $attempt,
                ]);

                // Yield rate limit message to client with retry suggestion
                yield "\n\n❌ **Rate Limit Exceeded**\n\nOpenAI API usage limit has been reached. Please try again in a few minutes or check your OpenAI usage limits.\n\n";
                throw $e;

            } catch (\OpenAI\Exceptions\UnauthorizedException $e) {
                Log::error('OpenAI Provider - Unauthorized during streaming', [
                    'error' => $e->getMessage(),
                    'model' => $model,
                    'attempt' => $attempt,
                ]);

                // Yield authorization error message
                yield "\n\n❌ **Authentication Error**\n\nOpenAI API key is invalid or expired. Please check your API configuration.\n\n";
                throw $e;

            } catch (\Exception $e) {
                $lastException = $e;
                $errorMessage = $e->getMessage();
                
                // Check if this is a recoverable stream/connection error
                $isRecoverableError = 
                    str_contains(strtolower($errorMessage), 'unable to read') ||
                    str_contains(strtolower($errorMessage), 'stream') ||
                    str_contains(strtolower($errorMessage), 'connection') ||
                    str_contains(strtolower($errorMessage), 'timeout') ||
                    str_contains(strtolower($errorMessage), 'reset') ||
                    str_contains(strtolower($errorMessage), 'broken pipe') ||
                    str_contains(strtolower($errorMessage), 'network') ||
                    $e instanceof \GuzzleHttp\Exception\ConnectException ||
                    $e instanceof \GuzzleHttp\Exception\RequestException;

                if ($isRecoverableError && $attempt < $maxRetries) {
                    $delay = $retryDelay * pow(2, $attempt - 1); // Exponential backoff: 2s, 4s, 8s
                    
                    Log::warning("OpenAI Provider - Stream connection error, retrying in {$delay}s", [
                        'error' => $errorMessage,
                        'error_class' => get_class($e),
                        'model' => $model,
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'next_delay' => $delay,
                    ]);

                    sleep($delay);
                    continue; // Retry
                }

                // Non-recoverable error or max retries reached
                Log::error('OpenAI Provider - Stream generation failed', [
                    'error' => $errorMessage,
                    'error_class' => get_class($e),
                    'model' => $model,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'trace' => $e->getTraceAsString(),
                ]);

                if (config('activity.ai_provider_calls', true)) {
                    ActivityLog::record(
                        'ai.call.openai_stream_failed',
                        'OpenAI stream failed',
                        null,
                        $userId ? (int) $userId : null,
                        array_filter([
                            'provider' => 'openai',
                            'feature' => $feature,
                            'model' => $model,
                            'temperature' => $temperature,
                            'max_tokens' => $maxTokens,
                            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                            'prompt_length' => strlen($prompt),
                            'attempt' => $attempt,
                            'error' => $errorMessage,
                            'error_class' => get_class($e),
                        ], fn ($v) => $v !== null)
                    );
                }

                // Yield error message to client
                yield "\n\n❌ **Generation Error**\n\nError generating content with OpenAI: " . $errorMessage . "\n\n";
                throw $e;
            }
        }

        // This should not be reached, but just in case
        if ($lastException) {
            throw $lastException;
        }
    }

    public function isAvailable(): bool
    {
        try {
            // Check if API key is configured
            if (empty(config('openai.api_key'))) {
                Log::warning('OpenAI Provider - API key not configured');

                return false;
            }

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

        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            // Rate limit doesn't mean the service is unavailable, just temporarily limited
            Log::info('OpenAI Provider - Rate limited but service is available', [
                'error' => $e->getMessage(),
            ]);

            return true; // Return true because the service is working, just rate limited

        } catch (\OpenAI\Exceptions\UnauthorizedException $e) {
            // Invalid API key
            Log::error('OpenAI Provider - Invalid API key', [
                'error' => $e->getMessage(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::warning('OpenAI Provider - Availability check failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
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
