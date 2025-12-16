<?php

namespace App\Services;

class AIAutocompleteService
{
    public function __construct(private AIContentGenerator $aiGenerator) {}

    public function generateCompletion(
        string $textBefore,
        string $textAfter,
        int $chapterNumber,
        string $chapterTitle,
        string $chapterOutline,
        string $sectionHeading,
        string $sectionOutline,
        string $projectTopic
    ): array {
        $before = trim($textBefore);
        $after = trim($textAfter);
        $sectionHeading = trim($sectionHeading);
        $sectionOutline = trim($sectionOutline);

        $prompt = <<<PROMPT
You are an academic writing autocomplete engine.

Task: Continue the user's current paragraph with a short, natural completion that matches the existing tone and does NOT repeat what is already written.

Context:
- Project topic: {$projectTopic}
- Chapter: {$chapterNumber} ({$chapterTitle})
- Chapter outline: {$chapterOutline}
PROMPT;

        if ($sectionHeading !== '') {
            $prompt .= "\n- Current section heading: {$sectionHeading}";
        }
        if ($sectionOutline !== '') {
            $prompt .= "\n- Section outline: {$sectionOutline}";
        }

        $prompt .= <<<PROMPT

Text before cursor:
{$before}

Text after cursor (for context, do not rewrite):
{$after}

Rules:
- Return ONLY the completion text (no quotes, no labels).
- Keep it short (roughly 8–25 words, 1–2 sentences max).
- Do not include leading whitespace unless necessary for punctuation.
- Do not introduce a new heading; stay in paragraph text.
- Stay on-topic and maintain continuity with the preceding paragraphs.
- Prefer completing the current sentence before starting a new one.
PROMPT;

        $completion = trim((string) $this->aiGenerator->generate($prompt, [
            'temperature' => 0.3,
            'max_tokens' => 120,
        ]));

        return [
            'completion' => $completion,
            'confidence' => $completion === '' ? 0.0 : 0.7,
        ];
    }
}
