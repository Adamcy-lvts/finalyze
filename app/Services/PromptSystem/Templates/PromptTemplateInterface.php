<?php

namespace App\Services\PromptSystem\Templates;

use App\Models\Project;
use App\Services\PromptSystem\ContentRequirements;

interface PromptTemplateInterface
{
    /**
     * Get the system prompt for AI context
     */
    public function getSystemPrompt(): string;

    /**
     * Build the chapter-specific prompt
     */
    public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string;

    /**
     * Get table requirements for this template
     */
    public function getTableRequirements(int $chapterNumber): array;

    /**
     * Get diagram requirements for this template
     */
    public function getDiagramRequirements(int $chapterNumber): array;

    /**
     * Get calculation requirements for this template
     */
    public function getCalculationRequirements(int $chapterNumber): array;

    /**
     * Get code requirements for this template
     */
    public function getCodeRequirements(int $chapterNumber): array;

    /**
     * Get placeholder rules for this template
     */
    public function getPlaceholderRules(int $chapterNumber): array;

    /**
     * Get recommended tools for this template
     */
    public function getRecommendedTools(): array;

    /**
     * Get the template priority (higher = more specific)
     */
    public function getPriority(): int;

    /**
     * Check if this template supports the given chapter type
     */
    public function supportsChapterType(string $chapterType): bool;
}
