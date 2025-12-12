<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait TopicTextHelpers
{
    /**
     * Convert markdown/plaintext descriptions to safe HTML for storage/display.
     */
    private function convertMarkdownToHtml(?string $text): string
    {
        $content = trim($text ?? '');

        if ($content === '') {
            return '';
        }

        // If HTML tags are already present, assume it has been converted.
        if ($content !== strip_tags($content)) {
            return $content;
        }

        try {
            return Str::markdown($content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Description markdown conversion failed, using escaped text', [
                'error' => $e->getMessage(),
            ]);

            return nl2br(e($content));
        }
    }

    /**
     * Lightly sanitize topic descriptions while preserving basic formatting.
     */
    private function cleanTopicDescription(?string $description): string
    {
        if (! $description) {
            return '';
        }

        // Remove script/style tags and keep basic text formatting
        $description = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $description) ?? $description;

        // Allow basic tags only
        $allowedTags = '<p><br><strong><em><ul><ol><li><b><i>';
        $sanitized = strip_tags($description, $allowedTags);

        // Collapse excess whitespace
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;

        return trim($sanitized);
    }
}
