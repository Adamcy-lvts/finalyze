<?php

namespace App\Services\AI\Providers;

interface AIProviderInterface
{
    /**
     * Generate content synchronously
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Generate content with streaming
     */
    public function streamGenerate(string $prompt, array $options = []): \Generator;

    /**
     * Check if provider is available and working
     */
    public function isAvailable(): bool;

    /**
     * Get provider name for logging
     */
    public function getName(): string;

    /**
     * Get provider capabilities/limits
     */
    public function getCapabilities(): array;

    /**
     * Get estimated cost per 1000 tokens
     */
    public function getCostPer1KTokens(): float;
}
