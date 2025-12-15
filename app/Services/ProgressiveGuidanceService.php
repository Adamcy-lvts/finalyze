<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Str;

class ProgressiveGuidanceService
{
    public function __construct(
        private AIContentGenerator $aiGenerator
    ) {}

    /**
     * Analyze writing progress and generate next steps
     */
    public function analyzeAndGuide(Chapter $chapter, array $frontendAnalysis): array
    {
        $project = $chapter->project;
        $wordCount = $frontendAnalysis['word_count'] ?? 0;
        $content = $frontendAnalysis['content_excerpt'] ?? ($frontendAnalysis['content'] ?? '');

        // Determine current stage
        $stage = $this->determineWritingStage($chapter, $frontendAnalysis);

        // Calculate completion percentage
        $completionPercentage = $this->calculateCompletionPercentage($chapter, $frontendAnalysis);

        // Generate next steps
        $nextSteps = $this->generateNextSteps($chapter, $project, $stage, $frontendAnalysis, $content);

        // Generate contextual tip
        $contextualTip = $this->generateContextualTip($chapter, $stage, $frontendAnalysis);

        return [
            'stage' => $stage,
            'stage_label' => $this->getStageLabel($stage),
            'completion_percentage' => $completionPercentage,
            'next_steps' => $nextSteps,
            'contextual_tip' => $contextualTip,
            'writing_milestones' => $this->getWritingMilestones($chapter, $frontendAnalysis),
        ];
    }

    /**
     * Determine current writing stage
     */
    private function determineWritingStage(Chapter $chapter, array $analysis): string
    {
        $wordCount = $analysis['word_count'] ?? 0;
        $outline = array_map(fn ($h) => strtolower(trim((string) $h)), $analysis['outline'] ?? []);
        $hasIntro = (bool) ($analysis['has_introduction'] ?? false) || collect($outline)->contains(fn ($h) => str_contains($h, 'introduction'));
        $hasConclusion = (bool) ($analysis['has_conclusion'] ?? false) || collect($outline)->contains(fn ($h) => str_contains($h, 'conclusion'));
        $citationCount = $analysis['citation_count'] ?? 0;
        $targetWordCount = $chapter->target_word_count ?? 1500;
        $wordRatio = $targetWordCount > 0 ? ($wordCount / $targetWordCount) : 0;

        // Empty or very minimal
        if ($wordCount < 50) {
            return 'planning';
        }

        // Early writing (or missing intro for early chapters)
        if ($wordCount < 250 || (! $hasIntro && $chapter->chapter_number <= 2)) {
            return 'introduction';
        }

        // Body development
        if ($hasIntro && ! $hasConclusion && $wordRatio < 0.7) {
            return 'body_development';
        }

        // Advanced body or near completion
        if ($hasIntro && ! $hasConclusion && $wordRatio >= 0.7) {
            return 'body_advanced';
        }

        // Has conclusion
        if ($hasConclusion) {
            return 'refinement';
        }

        return 'body_development';
    }

    /**
     * Calculate completion percentage
     */
    private function calculateCompletionPercentage(Chapter $chapter, array $analysis): int
    {
        $wordCount = $analysis['word_count'] ?? 0;
        $targetWordCount = $chapter->target_word_count ?? 1500;
        $outline = array_map(fn ($h) => strtolower(trim((string) $h)), $analysis['outline'] ?? []);
        $hasIntro = (bool) ($analysis['has_introduction'] ?? false) || collect($outline)->contains(fn ($h) => str_contains($h, 'introduction'));
        $hasConclusion = (bool) ($analysis['has_conclusion'] ?? false) || collect($outline)->contains(fn ($h) => str_contains($h, 'conclusion'));
        $citationCount = $analysis['citation_count'] ?? 0;
        $tableCount = $analysis['table_count'] ?? 0;

        $score = 0;

        // Word count (50% weight)
        $wordScore = min(100, ($wordCount / $targetWordCount) * 100);
        $score += $wordScore * 0.5;

        // Structure elements (30% weight)
        if ($hasIntro) {
            $score += 15;
        }
        if ($hasConclusion) {
            $score += 15;
        }

        // Academic elements (20% weight)
        if ($citationCount >= 3) {
            $score += 10;
        } elseif ($citationCount > 0) {
            $score += 5;
        }

        // Headings coverage (lightweight structural signal)
        $headingBonus = 0;
        if (count($outline) > 0) {
            // Reward having at least a few headings in longer chapters.
            $headingBonus = min(10, (int) floor(count($outline) * 2));
        }
        $score += $headingBonus;

        // Data visualization for methodology chapters
        if ($chapter->chapter_number === 3 && $tableCount > 0) {
            $score += 10;
        }

        return min(100, (int) $score);
    }

    /**
     * Generate next steps based on current progress
     */
    private function generateNextSteps(
        Chapter $chapter,
        Project $project,
        string $stage,
        array $analysis,
        string $content
    ): array {
        // Use AI to generate contextual next steps
        $prompt = $this->buildNextStepsPrompt($chapter, $project, $stage, $analysis, $content);

        try {
            $aiResponse = $this->aiGenerator->generate($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 400,
            ]);

            // Parse AI response into steps array
            return $this->parseStepsFromAI($aiResponse);
        } catch (\Exception $e) {
            // Fallback to rule-based steps
            return $this->getFallbackNextSteps($stage, $analysis);
        }
    }

    /**
     * Generate contextual tip
     */
    private function generateContextualTip(Chapter $chapter, string $stage, array $analysis): ?string
    {
        $wordCount = $analysis['word_count'] ?? 0;
        $citationCount = $analysis['citation_count'] ?? 0;
        $hasIntro = $analysis['has_introduction'] ?? false;

        return match ($stage) {
            'planning' => "Start with a brief introduction to set the context. Don't worry about perfection - just get your ideas down!",
            'introduction' => $hasIntro
                ? 'Great start! Now develop your main points with supporting evidence. Each paragraph should focus on one key idea.'
                : 'Consider starting with a clear introduction that outlines what this chapter will cover and why it matters.',
            'body_development' => $citationCount === 0
                ? 'Remember to add citations as you make claims. Every significant statement should be backed by evidence.'
                : "You're making good progress! Keep developing your arguments and connecting ideas logically.",
            'body_advanced' => "You're nearly there! Start thinking about how to tie everything together in a strong conclusion.",
            'refinement' => 'Excellent work! Review your chapter for clarity, check all citations are formatted correctly, and ensure smooth transitions between paragraphs.',
            default => null,
        };
    }

    /**
     * Get writing milestones checklist
     */
    private function getWritingMilestones(Chapter $chapter, array $analysis): array
    {
        $wordCount = $analysis['word_count'] ?? 0;
        $hasIntro = $analysis['has_introduction'] ?? false;
        $hasConclusion = $analysis['has_conclusion'] ?? false;
        $citationCount = $analysis['citation_count'] ?? 0;
        $tableCount = $analysis['table_count'] ?? 0;
        $targetWords = $chapter->target_word_count ?? 1500;

        $milestones = [
            [
                'id' => 'started',
                'label' => 'Started writing',
                'completed' => $wordCount > 0,
            ],
            [
                'id' => 'introduction',
                'label' => 'Introduction written',
                'completed' => $hasIntro,
            ],
            [
                'id' => 'word_count',
                'label' => "Reached {$targetWords} words",
                'completed' => $wordCount >= $targetWords,
            ],
            [
                'id' => 'citations',
                'label' => 'Added citations (3+)',
                'completed' => $citationCount >= 3,
            ],
        ];

        // Add chapter-specific milestones
        if ($chapter->chapter_number === 3) {
            // Methodology chapter
            $milestones[] = [
                'id' => 'tables',
                'label' => 'Added tables/figures',
                'completed' => $tableCount > 0,
            ];
        }

        $milestones[] = [
            'id' => 'conclusion',
            'label' => 'Conclusion written',
            'completed' => $hasConclusion,
        ];

        return $milestones;
    }

    /**
     * Get stage label for display
     */
    private function getStageLabel(string $stage): string
    {
        return match ($stage) {
            'planning' => 'Getting Started',
            'introduction' => 'Writing Introduction',
            'body_development' => 'Developing Body',
            'body_advanced' => 'Advanced Writing',
            'refinement' => 'Refinement & Review',
            default => 'Writing in Progress',
        };
    }

    /**
     * Build prompt for AI next steps generation
     */
    private function buildNextStepsPrompt(
        Chapter $chapter,
        Project $project,
        string $stage,
        array $analysis,
        string $content
    ): string {
        $wordCount = $analysis['word_count'] ?? 0;
        $citationCount = $analysis['citation_count'] ?? 0;
        $tableCount = $analysis['table_count'] ?? 0;
        $hasIntro = $analysis['has_introduction'] ?? false;
        $hasConclusion = $analysis['has_conclusion'] ?? false;
        $outline = array_slice(array_values($analysis['outline'] ?? []), 0, 20);

        // Get a snippet of current content (last 300 chars)
        $contentSnippet = Str::limit($content, 300);
        $outlineBlock = empty($outline)
            ? '(none)'
            : "- ".implode("\n- ", array_map('strval', $outline));

        return <<<PROMPT
You are an academic writing coach providing real-time guidance to a student writing their project.

PROJECT CONTEXT:
- Title: {$project->title}
- Type: {$project->projectType}
- Course: {$project->course}
- Field: {$project->field_of_study}

CHAPTER CONTEXT:
- Chapter {$chapter->chapter_number}: {$chapter->title}
- Current word count: {$wordCount}
- Has introduction: {$this->boolToString($hasIntro)}
- Has conclusion: {$this->boolToString($hasConclusion)}
- Citations: {$citationCount}
- Tables/Figures: {$tableCount}
- Writing stage: {$this->getStageLabel($stage)}

RECENT CONTENT:
{$contentSnippet}

OUTLINE (headings found):
{$outlineBlock}

TASK:
Generate 3-5 actionable next steps for the student to continue writing. Each step should be:
- Specific and actionable (not vague like "continue writing")
- Appropriate for their current progress
- Encouraging and supportive
- Progressive (build on what's already written)

OUTPUT FORMAT (required):
Return exactly ONE machine-readable JSON block wrapped in <NEXT_STEPS_JSON>...</NEXT_STEPS_JSON>.
Schema:
{
  "steps": [
    {
      "text": "string (<= 120 chars)",
      "action": "none|open_citation_helper|insert_text",
      "payload": { "text": "string" } // only for insert_text
    }
  ]
}
Rules:
- Always return 3-5 steps.
- Use action=open_citation_helper when step is about adding citations.
- Use action=insert_text only when you can provide a short template snippet to insert (<= 400 chars).
- Otherwise use action=none.
- Do NOT include any text outside the <NEXT_STEPS_JSON> block.

Examples of good steps:
- "Add a thesis statement at the end of your introduction"
- "Introduce your first main argument with evidence"
- "Insert a citation for the claim in paragraph 2"
- "Add a table showing your methodology framework"
- "Write a concluding paragraph summarizing key points"

Generate the next steps now:
PROMPT;
    }

    /**
     * Parse AI response into steps array
     */
    private function parseStepsFromAI(string $aiResponse): array
    {
        $steps = [];

        // Prefer JSON block.
        if (preg_match('/<NEXT_STEPS_JSON>\s*([\s\S]*?)\s*<\/NEXT_STEPS_JSON>/i', $aiResponse, $m)) {
            $raw = trim($m[1]);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && is_array($decoded['steps'] ?? null)) {
                foreach ($decoded['steps'] as $item) {
                    if (!is_array($item)) continue;
                    $text = is_string($item['text'] ?? null) ? trim($item['text']) : '';
                    if ($text === '') continue;

                    $action = is_string($item['action'] ?? null) ? $item['action'] : 'none';
                    $payload = is_array($item['payload'] ?? null) ? $item['payload'] : null;
                    if ($action === 'insert_text') {
                        $payloadText = is_string($payload['text'] ?? null) ? trim($payload['text']) : '';
                        if ($payloadText === '') {
                            $action = 'none';
                            $payload = null;
                        } else {
                            $payload = ['text' => mb_substr($payloadText, 0, 400)];
                        }
                    } else {
                        $payload = null;
                    }

                    $steps[] = [
                        'id' => $this->stableStepId($text, $action, $payload),
                        'text' => mb_substr($text, 0, 120),
                        'action' => $action,
                        'payload' => $payload,
                        'completed' => false,
                    ];
                }
            }
        }

        // Fallback: Split by numbered list patterns (1., 2., etc.)
        if (empty($steps)) {
            $lines = explode("\n", trim($aiResponse));

            foreach ($lines as $line) {
                $line = trim($line);
                // Match patterns like "1. " or "1) " or "- "
                if (preg_match('/^(\d+[\.\)]\s*|-\s*)(.+)$/', $line, $matches)) {
                    $stepText = trim($matches[2]);
                    if (! empty($stepText)) {
                        $steps[] = [
                            'id' => $this->stableStepId($stepText),
                            'text' => $stepText,
                            'action' => 'none',
                            'payload' => null,
                            'completed' => false,
                        ];
                    }
                }
            }

            // If parsing failed, try simpler approach
            if (empty($steps)) {
                $lines = array_filter(array_map('trim', $lines));
                foreach ($lines as $line) {
                    if (! empty($line)) {
                        $steps[] = [
                            'id' => $this->stableStepId($line),
                            'text' => $line,
                            'action' => 'none',
                            'payload' => null,
                            'completed' => false,
                        ];
                    }
                }
            }
        }

        return array_slice($steps, 0, 5); // Max 5 steps
    }

    private function stableStepId(string $text, string $action = 'none', ?array $payload = null): string
    {
        $normalized = Str::of($text)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
        $meta = $action;
        if ($action === 'insert_text' && is_array($payload) && isset($payload['text']) && is_string($payload['text'])) {
            $meta .= '|'.Str::of($payload['text'])->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
        }
        return 'step_'.substr(hash('sha1', $normalized.'|'.$meta), 0, 12);
    }

    /**
     * Get fallback next steps if AI fails
     */
    private function getFallbackNextSteps(string $stage, array $analysis): array
    {
        $steps = match ($stage) {
            'planning' => [
                ['id' => $this->stableStepId('Write a brief introduction paragraph'), 'text' => 'Write a brief introduction paragraph', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Outline 2-3 main points to cover'), 'text' => 'Outline 2-3 main points to cover', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Define key terms or concepts'), 'text' => 'Define key terms or concepts', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'introduction' => [
                ['id' => $this->stableStepId('Add a clear thesis statement'), 'text' => 'Add a clear thesis statement', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Provide background context'), 'text' => 'Provide background context', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Outline what the chapter will cover'), 'text' => 'Outline what the chapter will cover', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'body_development' => [
                ['id' => $this->stableStepId('Develop your first main argument'), 'text' => 'Develop your first main argument', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Add supporting evidence and citations'), 'text' => 'Add supporting evidence and citations', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Use clear topic sentences for paragraphs'), 'text' => 'Use clear topic sentences for paragraphs', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Connect ideas with transition phrases'), 'text' => 'Connect ideas with transition phrases', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'body_advanced' => [
                ['id' => $this->stableStepId('Add more evidence for your claims'), 'text' => 'Add more evidence for your claims', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Consider adding tables or figures'), 'text' => 'Consider adding tables or figures', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Check all citations are formatted correctly'), 'text' => 'Check all citations are formatted correctly', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Start writing your conclusion'), 'text' => 'Start writing your conclusion', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'refinement' => [
                ['id' => $this->stableStepId('Review for clarity and coherence'), 'text' => 'Review for clarity and coherence', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Check grammar and spelling'), 'text' => 'Check grammar and spelling', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Ensure consistent citation format'), 'text' => 'Ensure consistent citation format', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Verify all claims have evidence'), 'text' => 'Verify all claims have evidence', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            default => [
                ['id' => $this->stableStepId('Continue developing your main points'), 'text' => 'Continue developing your main points', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Add citations for your claims'), 'text' => 'Add citations for your claims', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Use clear paragraph structure'), 'text' => 'Use clear paragraph structure', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
        };

        return $steps;
    }

    /**
     * Convert boolean to string for prompts
     */
    private function boolToString(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
