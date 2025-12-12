<?php

namespace App\Services\Topics;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TopicTextService
{
    /**
     * Convert markdown/plaintext to safe HTML.
     */
    public function convertMarkdownToHtml(?string $text): string
    {
        $content = trim($text ?? '');

        if ($content === '') {
            return '';
        }

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
    public function cleanTopicDescription(?string $description): string
    {
        if (! $description) {
            return '';
        }

        $description = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $description) ?? $description;

        $allowedTags = '<p><br><strong><em><ul><ol><li><b><i>';
        $sanitized = strip_tags($description, $allowedTags);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;

        return trim($sanitized);
    }
}
