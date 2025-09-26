<?php

namespace App\Services;

use App\Services\AI\Providers\AIProviderInterface;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Generator;
use Illuminate\Support\Facades\Log;

class AIContentGenerator
{
    private array $providers = [];

    private ?AIProviderInterface $activeProvider = null;

    private array $providerHistory = [];

    public function __construct()
    {
        // Register providers in priority order (configurable via environment)
        $this->providers = $this->getConfiguredProviders();

        // Select the best available provider - but don't throw on failure
        $this->selectProviderSafely();
    }

    /**
     * Get providers based on configuration
     */
    private function getConfiguredProviders(): array
    {
        $defaultOrder = ['openai', 'claude']; // Default priority
        $configuredOrder = config('ai.provider_priority', $defaultOrder);

        $availableProviders = [
            'openai' => new OpenAIProvider,
            'claude' => new ClaudeProvider,
            // 'gemini' => new GeminiProvider(),
            // 'together' => new TogetherAIProvider(),
        ];

        $providers = [];
        foreach ($configuredOrder as $providerName) {
            if (isset($availableProviders[$providerName])) {
                $providers[] = $availableProviders[$providerName];
            }
        }

        // Add any remaining providers not in config
        foreach ($availableProviders as $name => $provider) {
            if (! in_array($name, $configuredOrder)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Select the best available provider - safe version that doesn't throw
     */
    private function selectProviderSafely(): void
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                $this->activeProvider = $provider;
                $this->providerHistory[] = [
                    'provider' => $provider->getName(),
                    'selected_at' => now(),
                    'cost_per_1k' => $provider->getCostPer1KTokens(),
                ];

                Log::info('AI Provider selected', [
                    'provider' => $provider->getName(),
                    'cost_per_1k_tokens' => $provider->getCostPer1KTokens(),
                    'capabilities' => $provider->getCapabilities(),
                ]);

                return;
            }
        }

        // No providers available - log but don't throw
        Log::warning('No AI providers are currently available - service will operate in offline mode');
        $this->activeProvider = null;
    }

    /**
     * Select the best available provider
     */
    private function selectProvider(): void
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                $this->activeProvider = $provider;
                $this->providerHistory[] = [
                    'provider' => $provider->getName(),
                    'selected_at' => now(),
                    'cost_per_1k' => $provider->getCostPer1KTokens(),
                ];

                Log::info('AI Provider selected', [
                    'provider' => $provider->getName(),
                    'cost_per_1k_tokens' => $provider->getCostPer1KTokens(),
                    'capabilities' => $provider->getCapabilities(),
                ]);

                return;
            }
        }

        throw new \Exception('No AI providers are currently available');
    }

    /**
     * Stream AI content generation with provider fallback
     *
     * @param  string  $prompt  The prompt to generate content from
     * @param  array  $options  Generation options
     * @return Generator<string> Yields content chunks as they're generated
     */
    public function streamGenerate(string $prompt, array $options = []): Generator
    {
        if (! $this->activeProvider) {
            // Gracefully handle offline state
            Log::warning('AI generation attempted while offline', [
                'prompt_length' => strlen($prompt),
                'options' => $options,
            ]);

            yield 'AI services are currently unavailable. Please check your internet connection and try again.';

            return;
        }

        $maxRetries = count($this->providers);
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                Log::info('AI Content Generation - Starting stream', [
                    'provider' => $this->activeProvider->getName(),
                    'attempt' => $attempt + 1,
                    'prompt_length' => strlen($prompt),
                    'options' => $options,
                    'timestamp' => now()->toDateTimeString(),
                ]);

                yield from $this->activeProvider->streamGenerate($prompt, $options);

                Log::info('AI Content Generation - Stream completed successfully', [
                    'provider' => $this->activeProvider->getName(),
                    'attempt' => $attempt + 1,
                ]);

                return; // Success, exit the retry loop

            } catch (\Exception $e) {
                $attempt++;

                Log::error('AI Content Generation - Provider failed', [
                    'provider' => $this->activeProvider->getName(),
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'will_retry' => $attempt < $maxRetries,
                ]);

                // Try next provider if available
                if ($attempt < $maxRetries) {
                    $this->selectNextProvider();

                    continue;
                }

                // All providers failed
                Log::error('AI Content Generation - All providers failed', [
                    'total_attempts' => $attempt,
                    'last_error' => $e->getMessage(),
                ]);

                yield 'Error: All AI providers failed. '.$e->getMessage();
                throw new \Exception('All AI providers failed after '.$attempt.' attempts. Last error: '.$e->getMessage());
            }
        }
    }

    /**
     * Select the next available provider for failover
     */
    private function selectNextProvider(): void
    {
        $currentIndex = array_search($this->activeProvider, $this->providers);
        $nextProviders = array_slice($this->providers, $currentIndex + 1);

        foreach ($nextProviders as $provider) {
            if ($provider->isAvailable()) {
                $this->activeProvider = $provider;

                Log::info('AI Provider failover', [
                    'new_provider' => $provider->getName(),
                    'cost_per_1k_tokens' => $provider->getCostPer1KTokens(),
                ]);

                return;
            }
        }

        // No more providers available
        $this->activeProvider = null;
    }

    /**
     * Generate content synchronously with provider fallback
     *
     * @param  string  $prompt  The prompt to generate content from
     * @param  array  $options  Generation options
     * @return string The generated content
     */
    public function generate(string $prompt, array $options = []): string
    {
        if (! $this->activeProvider) {
            // Gracefully handle offline state
            Log::warning('AI generation attempted while offline', [
                'prompt_length' => strlen($prompt),
                'options' => $options,
            ]);

            return 'AI services are currently unavailable. Please check your internet connection and try again.';
        }

        // Check if we have any providers at all
        if (! $this->activeProvider) {
            throw new \Exception('No AI providers are available for content generation');
        }

        $maxRetries = count($this->providers);
        $attempt = 0;

        while ($attempt < $maxRetries) {
            // Check if we have an active provider after potential failures
            if (! $this->activeProvider) {
                Log::error('AI Content Generation - No active provider available', [
                    'attempt' => $attempt + 1,
                    'providers_checked' => count($this->providers),
                ]);
                break;
            }

            try {
                Log::info('AI Content Generation - Starting synchronous generation', [
                    'provider' => $this->activeProvider->getName(),
                    'attempt' => $attempt + 1,
                    'prompt_length' => strlen($prompt),
                    'options' => $options,
                    'timestamp' => now()->toDateTimeString(),
                ]);

                $content = $this->activeProvider->generate($prompt, $options);

                Log::info('AI Content Generation - Synchronous generation completed', [
                    'provider' => $this->activeProvider->getName(),
                    'content_length' => strlen($content),
                    'word_count' => str_word_count($content),
                ]);

                return $content;

            } catch (\Exception $e) {
                $attempt++;

                Log::error('AI Content Generation - Provider failed', [
                    'provider' => $this->activeProvider->getName(),
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'will_retry' => $attempt < $maxRetries,
                ]);

                // Try next provider if available
                if ($attempt < $maxRetries) {
                    $this->selectNextProvider();

                    continue;
                }

                // All providers failed
                throw new \Exception('All AI providers failed after '.$attempt.' attempts. Last error: '.$e->getMessage());
            }
        }

        throw new \Exception('Unexpected error in generate method');
    }

    /**
     * Generate content with specific chapter requirements
     *
     * @param  string  $chapterTitle  The title of the chapter
     * @param  array  $requirements  Specific requirements for the chapter
     * @param  string  $context  Additional context from previous chapters
     * @return Generator<string>
     */
    public function generateChapter(string $chapterTitle, array $requirements = [], string $context = ''): Generator
    {
        $prompt = "Write a comprehensive academic chapter titled: {$chapterTitle}\n\n";

        if (! empty($context)) {
            $prompt .= "Context from previous work:\n{$context}\n\n";
        }

        if (! empty($requirements)) {
            $prompt .= "Specific requirements:\n";
            foreach ($requirements as $requirement) {
                $prompt .= "- {$requirement}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Write the complete chapter with proper academic structure, including:
- Clear introduction to the chapter's purpose
- Well-organized sections with appropriate headings
- Comprehensive content with proper depth
- Logical flow and transitions between sections
- Academic citations in APA format where appropriate
- Professional conclusion that ties to the next chapter

CITATION REQUIREMENTS:
- Use only REAL, VERIFIABLE sources - never cite fake or fabricated references
- Format all citations in proper APA style: (Author, Year) for in-text citations
- If you're unsure about a source's accuracy or existence, mark it as [UNVERIFIED] instead of creating a fake citation
- Example of proper in-text citation: (Smith, 2020) or (Johnson & Brown, 2019)
- When making claims that need support, either cite real sources you're confident about, or clearly indicate the statement is general knowledge
- It's better to have fewer citations that are real than many citations that are questionable

Maintain formal academic tone throughout.";

        return $this->streamGenerate($prompt);
    }

    /**
     * Set AI model for generation
     *
     * @param  string  $model  The model to use (e.g., 'gpt-4o-mini', 'gpt-4-turbo')
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set temperature for generation
     *
     * @param  float  $temperature  Temperature between 0-2
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = max(0, min(2, $temperature));

        return $this;
    }

    /**
     * Set max tokens for generation
     *
     * @param  int  $maxTokens  Maximum tokens to generate
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = max(1, min(4096, $maxTokens));

        return $this;
    }

    /**
     * Create a new instance with higher quality model for important content
     */
    public function withHighQualityModel(): self
    {
        return (clone $this)->setModel('gpt-4o');
    }

    /**
     * Check if AI service is currently available
     */
    public function isAvailable(): bool
    {
        return $this->activeProvider !== null;
    }

    /**
     * Get current active provider info
     */
    public function getActiveProvider(): ?AIProviderInterface
    {
        return $this->activeProvider;
    }

    /**
     * Get provider history for analytics
     */
    public function getProviderHistory(): array
    {
        return $this->providerHistory;
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        return array_filter($this->providers, fn ($provider) => $provider->isAvailable());
    }

    /**
     * Force use of specific provider by name
     */
    public function useProvider(string $providerName): self
    {
        foreach ($this->providers as $provider) {
            if ($provider->getName() === $providerName && $provider->isAvailable()) {
                $this->activeProvider = $provider;

                Log::info('AI Provider manually selected', [
                    'provider' => $provider->getName(),
                    'cost_per_1k_tokens' => $provider->getCostPer1KTokens(),
                ]);

                return $this;
            }
        }

        throw new \Exception("Provider '{$providerName}' not available");
    }

    /**
     * Generate content with automatic model selection based on content type
     */
    public function generateOptimized(string $prompt, string $contentType = 'general'): Generator
    {
        $options = $this->getOptimizedOptions($contentType);

        return $this->streamGenerate($prompt, $options);
    }

    /**
     * Get optimized options based on content type
     */
    private function getOptimizedOptions(string $contentType): array
    {
        return match ($contentType) {
            'introduction', 'conclusion' => [
                'model' => 'gpt-4o', // High quality for important chapters
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ],
            'literature_review', 'methodology' => [
                'model' => 'gpt-4o-mini', // Cost-effective for structured content
                'temperature' => 0.6,
                'max_tokens' => 4000,
            ],
            'draft', 'outline' => [
                'model' => 'gpt-4o-mini', // Fast and cheap for drafts
                'temperature' => 0.8,
                'max_tokens' => 2000,
            ],
            default => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ]
        };
    }

    /**
     * Generate topics with intelligent model selection based on academic context
     */
    public function generateTopicsOptimized(string $prompt, array $academicContext = []): Generator
    {
        $options = $this->getTopicGenerationOptions($academicContext);

        return $this->streamGenerate($prompt, $options);
    }

    /**
     * Generate topic descriptions with intelligent model selection
     */
    public function generateTopicDescriptionOptimized(string $prompt, array $academicContext = []): string
    {
        $options = $this->getTopicDescriptionOptions($academicContext);

        return $this->generate($prompt, $options);
    }

    /**
     * Get optimized options for topic generation based on academic context
     */
    private function getTopicGenerationOptions(array $context): array
    {
        $fieldOfStudy = strtolower($context['field_of_study'] ?? '');
        $academicLevel = strtolower($context['academic_level'] ?? 'undergraduate');
        $university = strtolower($context['university'] ?? '');
        $faculty = strtolower($context['faculty'] ?? '');

        // High-complexity fields requiring premium models
        $highComplexityFields = [
            'artificial intelligence', 'machine learning', 'quantum computing',
            'biomedical engineering', 'aerospace engineering', 'nuclear engineering',
            'theoretical physics', 'advanced mathematics', 'biochemistry',
            'neuroscience', 'genetic engineering', 'robotics',
        ];

        // Premium universities requiring higher quality
        $premiumUniversities = [
            'university of ibadan', 'university of lagos', 'ahmadu bello university',
            'university of nigeria nsukka', 'obafemi awolowo university',
            'covenant university', 'american university of nigeria',
        ];

        // STEM fields requiring technical precision
        $stemFields = [
            'engineering', 'computer science', 'mathematics', 'physics',
            'chemistry', 'biology', 'medicine', 'pharmacy', 'agriculture',
        ];

        // Determine if high-quality model is needed
        $needsHighQuality =
            $academicLevel === 'phd' ||
            $academicLevel === 'masters' ||
            in_array($university, $premiumUniversities) ||
            $this->containsAny($fieldOfStudy, $highComplexityFields) ||
            $this->containsAny($faculty, ['medicine', 'engineering', 'science']);

        // Determine if technical precision is needed
        $needsTechnicalPrecision =
            $this->containsAny($fieldOfStudy, $stemFields) ||
            $this->containsAny($faculty, ['engineering', 'science', 'medicine', 'technology']);

        if ($needsHighQuality) {
            return [
                'model' => 'gpt-4o',           // Premium model for quality
                'temperature' => 0.6,          // Lower temp for precision
                'max_tokens' => 3500,
            ];
        } elseif ($needsTechnicalPrecision) {
            return [
                'model' => 'gpt-4o-mini',      // Good balance for technical content
                'temperature' => 0.5,          // Lower temp for technical accuracy
                'max_tokens' => 3000,
            ];
        } else {
            return [
                'model' => 'gpt-4o-mini',      // Cost-effective for general topics
                'temperature' => 0.7,          // Standard creativity
                'max_tokens' => 2500,
            ];
        }
    }

    /**
     * Get optimized options for topic descriptions based on academic context
     */
    private function getTopicDescriptionOptions(array $context): array
    {
        $academicLevel = strtolower($context['academic_level'] ?? 'undergraduate');

        // PhD and Masters need more sophisticated descriptions
        if ($academicLevel === 'phd') {
            return [
                'model' => 'gpt-4o',           // Highest quality for PhD
                'temperature' => 0.6,
                'max_tokens' => 2000,
            ];
        } elseif ($academicLevel === 'masters') {
            return [
                'model' => 'gpt-4o-mini',      // Good quality for Masters
                'temperature' => 0.6,
                'max_tokens' => 1500,
            ];
        } else {
            return [
                'model' => 'gpt-4o-mini',      // Cost-effective for undergraduate
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ];
        }
    }

    /**
     * Helper method to check if string contains any of the given terms
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
