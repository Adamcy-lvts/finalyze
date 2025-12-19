<?php

namespace App\Services\AI\Providers;

interface AIProviderInterface
{
    /**
     * Generate content synchronously
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Generate content from a chat message envelope.
     *
     * Message format:
     * [
     *   ['role' => 'system'|'user'|'assistant', 'content' => '...'],
     *   ...
     * ]
     */
    public function generateMessages(array $messages, array $options = []): string;

    /**
     * Generate content with streaming
     */
    public function streamGenerate(string $prompt, array $options = []): \Generator;

    /**
     * Stream content from a chat message envelope.
     *
     * Message format:
     * [
     *   ['role' => 'system'|'user'|'assistant', 'content' => '...'],
     *   ...
     * ]
     */
    public function streamGenerateMessages(array $messages, array $options = []): \Generator;

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
