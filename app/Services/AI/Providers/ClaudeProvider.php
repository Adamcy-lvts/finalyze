<?php

namespace App\Services\AI\Providers;

use Generator;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AIProviderInterface
{
    private const DEFAULT_SYSTEM_PROMPT = 'You are an expert academic writer specializing in thesis and dissertation writing. Follow the user instructions and maintain a formal academic tone. Use clear structure with headings when needed. Prefer the bullet symbol (•) for unordered lists unless the user explicitly requests a different format. Never use "&" — always write "and".';

    private ?string $apiKey;

    private string $model = 'claude-3-haiku-20240307'; // Cheaper model for now

    private int $maxTokens = 4000;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    public function generate(string $prompt, array $options = []): string
    {
        if (isset($options['messages']) && is_array($options['messages'])) {
            return $this->generateMessages($options['messages'], $options);
        }

        $model = $options['model'] ?? $this->model;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;
        $feature = $options['feature'] ?? null;
        $userId = $options['user_id'] ?? null;
        $startedAt = hrtime(true);

        Log::info('Claude Provider - Starting generation', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'max_tokens' => $maxTokens,
        ]);

        if (! $this->apiKey) {
            throw new \Exception('Claude API key not configured');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'content-type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => self::DEFAULT_SYSTEM_PROMPT."\n\n".$prompt,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                throw new \Exception('Claude API request failed: '.$response->body());
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            Log::info('Claude Provider - Generation completed', [
                'content_length' => strlen($content),
                'word_count' => str_word_count($content),
                'tokens_used' => $data['usage']['output_tokens'] ?? 0,
            ]);

            if (config('activity.ai_provider_calls', true)) {
                ActivityLog::record(
                    'ai.call.claude',
                    'Claude generation completed',
                    null,
                    $userId ? (int) $userId : null,
                    array_filter([
                        'provider' => 'claude',
                        'feature' => $feature,
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                        'duration_ms' => $durationMs,
                        'prompt_length' => strlen($prompt),
                        'content_length' => strlen($content),
                        'tokens' => [
                            'output' => $data['usage']['output_tokens'] ?? 0,
                            'input' => $data['usage']['input_tokens'] ?? null,
                        ],
                    ], fn ($v) => $v !== null)
                );
            }

            return $content;

        } catch (\Exception $e) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            Log::error('Claude Provider - Generation failed', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            if (config('activity.ai_provider_calls', true)) {
                ActivityLog::record(
                    'ai.call.claude_failed',
                    'Claude generation failed',
                    null,
                    $userId ? (int) $userId : null,
                    array_filter([
                        'provider' => 'claude',
                        'feature' => $feature,
                        'model' => $model,
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

    public function generateMessages(array $messages, array $options = []): string
    {
        $model = $options['model'] ?? $this->model;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;
        $feature = $options['feature'] ?? null;
        $userId = $options['user_id'] ?? null;
        $startedAt = hrtime(true);

        if (! $this->apiKey) {
            throw new \Exception('Claude API key not configured');
        }

        [$system, $anthropicMessages] = $this->normalizeMessagesForAnthropic($messages);

        Log::info('Claude Provider - Starting generation (messages)', [
            'model' => $model,
            'message_count' => count($anthropicMessages),
            'max_tokens' => $maxTokens,
            'has_system' => $system !== null,
        ]);

        try {
            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $anthropicMessages,
            ];

            if ($system !== null && $system !== '') {
                $payload['system'] = $system;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'content-type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', $payload);

            if (! $response->successful()) {
                throw new \Exception('Claude API request failed: '.$response->body());
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            Log::info('Claude Provider - Generation completed (messages)', [
                'content_length' => strlen($content),
                'word_count' => str_word_count($content),
                'tokens_used' => $data['usage']['output_tokens'] ?? 0,
            ]);

            if (config('activity.ai_provider_calls', true)) {
                ActivityLog::record(
                    'ai.call.claude',
                    'Claude generation completed',
                    null,
                    $userId ? (int) $userId : null,
                    array_filter([
                        'provider' => 'claude',
                        'feature' => $feature,
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                        'duration_ms' => $durationMs,
                        'message_count' => count($anthropicMessages),
                        'tokens' => [
                            'output' => $data['usage']['output_tokens'] ?? 0,
                            'input' => $data['usage']['input_tokens'] ?? null,
                        ],
                        'message_envelope' => true,
                    ], fn ($v) => $v !== null)
                );
            }

            return $content;

        } catch (\Exception $e) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            Log::error('Claude Provider - Generation failed (messages)', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            if (config('activity.ai_provider_calls', true)) {
                ActivityLog::record(
                    'ai.call.claude_failed',
                    'Claude generation failed',
                    null,
                    $userId ? (int) $userId : null,
                    array_filter([
                        'provider' => 'claude',
                        'feature' => $feature,
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                        'duration_ms' => $durationMs,
                        'error' => $e->getMessage(),
                        'message_envelope' => true,
                    ], fn ($v) => $v !== null)
                );
            }
            throw $e;
        }
    }

    public function streamGenerate(string $prompt, array $options = []): Generator
    {
        if (isset($options['messages']) && is_array($options['messages'])) {
            return $this->streamGenerateMessages($options['messages'], $options);
        }

        $model = $options['model'] ?? $this->model;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

        Log::info('Claude Provider - Starting stream generation', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'max_tokens' => $maxTokens,
        ]);

        if (! $this->apiKey) {
            throw new \Exception('Claude API key not configured');
        }

        try {
            // Claude streaming implementation would go here
            // For now, fall back to regular generation and yield in chunks
            $content = $this->generate($prompt, $options);

            // Simulate streaming by yielding content in chunks
            $words = explode(' ', $content);
            $currentChunk = '';

            foreach ($words as $index => $word) {
                $currentChunk .= $word.' ';

                // Yield every 5-10 words to simulate streaming
                if ($index % 8 === 0 && $index > 0) {
                    yield $currentChunk;
                    $currentChunk = '';
                    usleep(50000); // Small delay to simulate real streaming
                }
            }

            // Yield any remaining content
            if (! empty($currentChunk)) {
                yield $currentChunk;
            }

            Log::info('Claude Provider - Stream simulation completed');

        } catch (\Exception $e) {
            Log::error('Claude Provider - Stream generation failed', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            yield 'Error generating content with Claude: '.$e->getMessage();
            throw $e;
        }
    }

    public function streamGenerateMessages(array $messages, array $options = []): Generator
    {
        // Claude streaming isn't implemented; simulate like streamGenerate().
        $content = $this->generateMessages($messages, $options);

        $words = explode(' ', $content);
        $currentChunk = '';

        foreach ($words as $index => $word) {
            $currentChunk .= $word.' ';

            if ($index % 8 === 0 && $index > 0) {
                yield $currentChunk;
                $currentChunk = '';
                usleep(50000);
            }
        }

        if ($currentChunk !== '') {
            yield $currentChunk;
        }
    }

    private function normalizeMessagesForAnthropic(array $messages): array
    {
        $systemParts = [];
        $anthropicMessages = [];

        foreach ($messages as $msg) {
            if (! is_array($msg)) {
                continue;
            }

            $role = (string) ($msg['role'] ?? '');
            $content = (string) ($msg['content'] ?? '');

            if ($role === '' || $content === '') {
                continue;
            }

            if ($role === 'system') {
                $systemParts[] = $content;
                continue;
            }

            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $anthropicMessages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        if (empty($anthropicMessages)) {
            $anthropicMessages[] = [
                'role' => 'user',
                'content' => self::DEFAULT_SYSTEM_PROMPT,
            ];
        }

        $system = null;
        if (! empty($systemParts)) {
            $system = implode("\n\n", $systemParts);
        }

        return [$system, $anthropicMessages];
    }

    public function isAvailable(): bool
    {
        if (! $this->apiKey) {
            Log::warning('Claude Provider - No API key configured');

            return false;
        }

        try {
            // Simple test to check if Claude is working
            $response = Http::timeout(10)->withHeaders([
                'x-api-key' => $this->apiKey,
                'content-type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ]);

            if ($response->successful()) {
                Log::info('Claude Provider - Availability check passed');

                return true;
            } else {
                Log::warning('Claude Provider - Availability check failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return false;
            }

        } catch (\Exception $e) {
            Log::warning('Claude Provider - Availability check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'Anthropic Claude';
    }

    public function getCapabilities(): array
    {
        return [
            'models' => ['claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307'],
            'max_tokens' => 4000,
            'supports_streaming' => true, // Will be true once we implement real streaming
            'supports_system_prompt' => true,
            'languages' => ['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'],
        ];
    }

    public function getCostPer1KTokens(): float
    {
        return 0.25; // $0.25 per 1K input tokens for claude-3-haiku
    }
}
