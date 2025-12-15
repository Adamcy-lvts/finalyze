<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Str;

class ProgressiveGuidanceService
{
    public const ALGO_VERSION = 2;

    public function __construct(
        private AIContentGenerator $aiGenerator,
        private FacultyStructureService $facultyStructureService
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
        $targetWordCount = $chapter->target_word_count ?? 1500;
        $wordRatio = $targetWordCount > 0 ? ($wordCount / $targetWordCount) : 0;

        // Empty or very minimal
        if ($wordCount < 50) {
            return 'planning';
        }

        // Early drafting
        if ($wordRatio < 0.25) {
            return 'introduction';
        }

        // Developing
        if ($wordRatio < 0.7) {
            return 'body_development';
        }

        // Near completion
        if ($wordRatio < 1.0) {
            return 'body_advanced';
        }

        // At or above target word count: refine
        return 'refinement';
    }

    /**
     * Calculate completion percentage
     */
    private function calculateCompletionPercentage(Chapter $chapter, array $analysis): int
    {
        $wordCount = (int) ($analysis['word_count'] ?? 0);
        $targetWordCount = (int) ($chapter->target_word_count ?? 1500);
        $outline = is_array($analysis['outline'] ?? null) ? $analysis['outline'] : [];

        if ($targetWordCount <= 0) {
            return 0;
        }

        $wordProgress = min(1, $wordCount / $targetWordCount); // 0..1

        $expectedSections = $this->getExpectedSections($chapter);
        $requiredSections = array_values(array_filter($expectedSections, fn ($s) => (bool) ($s['is_required'] ?? true)));

        $sectionCoverage = null; // 0..1 or null when not applicable
        if (count($requiredSections) > 0) {
            if (count($outline) === 0) {
                $assumedWordsPerSection = 200;
                $sectionCoverage = min(1, $wordCount / (count($requiredSections) * $assumedWordsPerSection));
            } else {
                $matched = 0;
                foreach ($requiredSections as $section) {
                    if ($this->sectionAppearsInOutline($section, $outline)) {
                        $matched++;
                    }
                }
                $sectionCoverage = $matched / max(1, count($requiredSections));
            }
        }

        // Weighting:
        // - Word progress is consistent across disciplines.
        // - Section coverage only applies when the faculty structure defines sections.
        if ($sectionCoverage === null) {
            return (int) round($wordProgress * 100);
        }

        $weighted = ($wordProgress * 0.8) + ($sectionCoverage * 0.2);
        return (int) round(min(1, $weighted) * 100);
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
            $steps = $this->parseStepsFromAI($aiResponse);
            if (! empty($steps)) {
                return $steps;
            }

            // If parsing failed, fallback to rule-based steps (avoid showing raw JSON/tags).
            return $this->getFallbackNextSteps($stage, $analysis);
        } catch (\Exception $e) {
            // Fallback to rule-based steps
            return $this->getFallbackNextSteps($stage, $analysis);
        }
    }

    public function parseNextStepsResponse(string $aiResponse): array
    {
        return $this->parseStepsFromAI($aiResponse);
    }

    /**
     * Generate contextual tip
     */
    private function generateContextualTip(Chapter $chapter, string $stage, array $analysis): ?string
    {
        $wordCount = $analysis['word_count'] ?? 0;

        return match ($stage) {
            'planning' => "Start with a brief introduction to set the context. Don't worry about perfection - just get your ideas down!",
            'introduction' => 'Great start! Keep building your outline/sections and develop your main points one at a time.',
            'body_development' => "You're making good progress! Keep developing your arguments and connecting ideas logically.",
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
        $wordCount = (int) ($analysis['word_count'] ?? 0);
        $targetWords = (int) ($chapter->target_word_count ?? 1500);
        $outline = is_array($analysis['outline'] ?? null) ? $analysis['outline'] : [];

        $milestones = [
            [
                'id' => 'started',
                'label' => 'Started writing',
                'completed' => $wordCount > 0,
            ],
        ];

        $expectedSections = $this->getExpectedSections($chapter);
        $requiredSections = array_values(array_filter($expectedSections, fn ($s) => (bool) ($s['is_required'] ?? true)));
        foreach (array_slice($requiredSections, 0, 6) as $section) {
            $label = trim(($section['number'] ?? '') . ' ' . ($section['title'] ?? ''));
            $milestones[] = [
                'id' => 'section_'.md5(($section['number'] ?? '').'|'.($section['title'] ?? '')),
                'label' => $label !== '' ? $label : 'Section started',
                'completed' => $this->sectionAppearsInOutline($section, $outline),
            ];
        }

        $milestones[] = [
            'id' => 'word_count',
            'label' => "Reached {$targetWords} words",
            'completed' => $targetWords > 0 ? $wordCount >= $targetWords : false,
        ];

        $milestones[] = [
            'id' => 'marked_complete',
            'label' => 'Chapter marked complete',
            'completed' => ($chapter->status ?? null) === \App\Enums\ChapterStatus::Completed->value,
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
            'introduction' => 'Drafting',
            'body_development' => 'Developing Body',
            'body_advanced' => 'Near Completion',
            'refinement' => 'Refinement & Review',
            default => 'Writing in Progress',
        };
    }

    private function getExpectedSections(Chapter $chapter): array
    {
        $project = $chapter->project;
        if (! $project) {
            return [];
        }

        try {
            $structure = $this->facultyStructureService->getChapterStructure($project);
            $entry = collect($structure)->first(fn ($c) => (int) ($c['number'] ?? 0) === (int) $chapter->chapter_number);
            if (! is_array($entry)) {
                return [];
            }

            $sections = $entry['sections'] ?? [];
            if (! is_array($sections)) {
                return [];
            }

            return array_values(array_map(function ($s) {
                if (! is_array($s)) return null;
                return [
                    'number' => (string) ($s['number'] ?? ''),
                    'title' => (string) ($s['title'] ?? ''),
                    'is_required' => (bool) ($s['is_required'] ?? true),
                ];
            }, $sections));
        } catch (\Throwable) {
            return [];
        }
    }

    private function sectionAppearsInOutline(array $section, array $outline): bool
    {
        $sectionNumber = trim((string) ($section['number'] ?? ''));
        $sectionTitle = trim((string) ($section['title'] ?? ''));

        $normalizedTitle = $this->normalizeHeading($sectionTitle);
        $titleTokens = $this->tokenizeHeading($normalizedTitle);

        foreach ($outline as $headingRaw) {
            $heading = $this->normalizeHeading((string) $headingRaw);
            if ($heading === '') continue;

            if ($sectionNumber !== '' && preg_match('/^\s*'.preg_quote($sectionNumber, '/').'\b/', (string) $headingRaw)) {
                return true;
            }

            if ($normalizedTitle !== '' && str_contains($heading, $normalizedTitle)) {
                return true;
            }

            if (count($titleTokens) >= 3) {
                $headingTokens = $this->tokenizeHeading($heading);
                if ($this->tokenOverlap($titleTokens, $headingTokens) >= 0.6) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeHeading(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return trim($value);
    }

    private function tokenizeHeading(string $value): array
    {
        if ($value === '') return [];
        $tokens = preg_split('/\s+/u', $value) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= 3));
        return array_slice($tokens, 0, 12);
    }

    private function tokenOverlap(array $a, array $b): float
    {
        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        if (count($a) === 0 || count($b) === 0) return 0.0;
        $intersection = array_intersect($a, $b);
        return count($intersection) / max(count($a), count($b));
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
      "priority": "critical|optional",
      "action": "none|open_citation_helper|insert_text",
      "payload": { "text": "string" } // only for insert_text
    }
  ]
}
Rules:
- Always return 3-5 steps.
- Mark at most 3 steps as priority=critical (the truly crucial steps).
- Mark the rest as priority=optional.
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
        $rawJson = null;
        if (preg_match('/<NEXT_STEPS_JSON>\s*([\s\S]*?)\s*<\/NEXT_STEPS_JSON>/i', $aiResponse, $m)) {
            $rawJson = trim($m[1]);
        } elseif (stripos($aiResponse, '<NEXT_STEPS_JSON>') !== false) {
            // Sometimes models forget the closing tag; treat everything after the opening tag as the payload.
            $pos = stripos($aiResponse, '<NEXT_STEPS_JSON>');
            $rawJson = trim(substr($aiResponse, $pos + strlen('<NEXT_STEPS_JSON>')));
            $rawJson = preg_replace('/<\/NEXT_STEPS_JSON>\s*$/i', '', $rawJson ?? '');
        }

        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (! is_array($decoded)) {
                // Handle JSON that is double-escaped (e.g. {\"steps\": ...})
                $decoded = json_decode(stripcslashes($rawJson), true);
            }
            if (is_array($decoded) && is_array($decoded['steps'] ?? null)) {
                foreach ($decoded['steps'] as $item) {
                    if (!is_array($item)) continue;
                    $text = is_string($item['text'] ?? null) ? trim($item['text']) : '';
                    if ($text === '') continue;

                    $priority = is_string($item['priority'] ?? null) ? strtolower(trim($item['priority'])) : 'optional';
                    if (! in_array($priority, ['critical', 'optional'], true)) {
                        $priority = 'optional';
                    }

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
                        'priority' => $priority,
                        'action' => $action,
                        'payload' => $payload,
                        'completed' => false,
                    ];
                }
            }

            // If we saw a JSON marker but couldn't parse, treat as failure (fallback will handle).
            if (stripos($aiResponse, '<NEXT_STEPS_JSON>') !== false && empty($steps)) {
                return [];
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
                            'priority' => 'optional',
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
                        if (stripos($line, '<NEXT_STEPS_JSON>') !== false) {
                            // Avoid leaking machine-readable blocks into UI.
                            return [];
                        }
                        $steps[] = [
                            'id' => $this->stableStepId($line),
                            'text' => $line,
                            'priority' => 'optional',
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
                ['id' => $this->stableStepId('Write a brief introduction paragraph'), 'text' => 'Write a brief introduction paragraph', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Outline 2-3 main points to cover'), 'text' => 'Outline 2-3 main points to cover', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Define key terms or concepts'), 'text' => 'Define key terms or concepts', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'introduction' => [
                ['id' => $this->stableStepId('Add a clear thesis statement'), 'text' => 'Add a clear thesis statement', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Provide background context'), 'text' => 'Provide background context', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Outline what the chapter will cover'), 'text' => 'Outline what the chapter will cover', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'body_development' => [
                ['id' => $this->stableStepId('Develop your first main argument'), 'text' => 'Develop your first main argument', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Add supporting evidence and citations'), 'text' => 'Add supporting evidence and citations', 'priority' => 'critical', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Use clear topic sentences for paragraphs'), 'text' => 'Use clear topic sentences for paragraphs', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Connect ideas with transition phrases'), 'text' => 'Connect ideas with transition phrases', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'body_advanced' => [
                ['id' => $this->stableStepId('Check all citations are formatted correctly'), 'text' => 'Check all citations are formatted correctly', 'priority' => 'critical', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Start writing your conclusion'), 'text' => 'Start writing your conclusion', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Add more evidence for your claims'), 'text' => 'Add more evidence for your claims', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Consider adding tables or figures'), 'text' => 'Consider adding tables or figures', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            'refinement' => [
                ['id' => $this->stableStepId('Review for clarity and coherence'), 'text' => 'Review for clarity and coherence', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Ensure consistent citation format'), 'text' => 'Ensure consistent citation format', 'priority' => 'critical', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Check grammar and spelling'), 'text' => 'Check grammar and spelling', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Verify all claims have evidence'), 'text' => 'Verify all claims have evidence', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
            ],
            default => [
                ['id' => $this->stableStepId('Continue developing your main points'), 'text' => 'Continue developing your main points', 'priority' => 'critical', 'action' => 'none', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Add citations for your claims'), 'text' => 'Add citations for your claims', 'priority' => 'critical', 'action' => 'open_citation_helper', 'payload' => null, 'completed' => false],
                ['id' => $this->stableStepId('Use clear paragraph structure'), 'text' => 'Use clear paragraph structure', 'priority' => 'optional', 'action' => 'none', 'payload' => null, 'completed' => false],
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
