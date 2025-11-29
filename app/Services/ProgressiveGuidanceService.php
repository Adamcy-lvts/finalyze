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
        $content = $frontendAnalysis['content'] ?? '';

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
        $hasIntro = $analysis['has_introduction'] ?? false;
        $hasConclusion = $analysis['has_conclusion'] ?? false;
        $citationCount = $analysis['citation_count'] ?? 0;

        // Empty or very minimal
        if ($wordCount < 50) {
            return 'planning';
        }

        // Has introduction
        if ($hasIntro && $wordCount < 200) {
            return 'introduction';
        }

        // Body development
        if ($hasIntro && ! $hasConclusion && $wordCount < 800) {
            return 'body_development';
        }

        // Advanced body or near completion
        if ($hasIntro && ! $hasConclusion && $wordCount >= 800) {
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
        $hasIntro = $analysis['has_introduction'] ?? false;
        $hasConclusion = $analysis['has_conclusion'] ?? false;
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

        // Get a snippet of current content (last 300 chars)
        $contentSnippet = Str::limit($content, 300);

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

TASK:
Generate 3-5 actionable next steps for the student to continue writing. Each step should be:
- Specific and actionable (not vague like "continue writing")
- Appropriate for their current progress
- Encouraging and supportive
- Progressive (build on what's already written)

Format your response as a numbered list (1., 2., 3., etc.), with each step on a new line.
Keep each step concise (under 15 words).
Focus on WHAT TO DO NEXT, not what they've already done.

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
        // Split by numbered list patterns (1., 2., etc.)
        $lines = explode("\n", trim($aiResponse));
        $steps = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Match patterns like "1. " or "1) " or "- "
            if (preg_match('/^(\d+[\.\)]\s*|-\s*)(.+)$/', $line, $matches)) {
                $stepText = trim($matches[2]);
                if (! empty($stepText)) {
                    $steps[] = [
                        'id' => 'step_'.count($steps),
                        'text' => $stepText,
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
                        'id' => 'step_'.count($steps),
                        'text' => $line,
                        'completed' => false,
                    ];
                }
            }
        }

        return array_slice($steps, 0, 5); // Max 5 steps
    }

    /**
     * Get fallback next steps if AI fails
     */
    private function getFallbackNextSteps(string $stage, array $analysis): array
    {
        $steps = match ($stage) {
            'planning' => [
                ['id' => 'step_0', 'text' => 'Write a brief introduction paragraph', 'completed' => false],
                ['id' => 'step_1', 'text' => 'Outline 2-3 main points to cover', 'completed' => false],
                ['id' => 'step_2', 'text' => 'Define key terms or concepts', 'completed' => false],
            ],
            'introduction' => [
                ['id' => 'step_0', 'text' => 'Add a clear thesis statement', 'completed' => false],
                ['id' => 'step_1', 'text' => 'Provide background context', 'completed' => false],
                ['id' => 'step_2', 'text' => 'Outline what the chapter will cover', 'completed' => false],
            ],
            'body_development' => [
                ['id' => 'step_0', 'text' => 'Develop your first main argument', 'completed' => false],
                ['id' => 'step_1', 'text' => 'Add supporting evidence and citations', 'completed' => false],
                ['id' => 'step_2', 'text' => 'Use clear topic sentences for paragraphs', 'completed' => false],
                ['id' => 'step_3', 'text' => 'Connect ideas with transition phrases', 'completed' => false],
            ],
            'body_advanced' => [
                ['id' => 'step_0', 'text' => 'Add more evidence for your claims', 'completed' => false],
                ['id' => 'step_1', 'text' => 'Consider adding tables or figures', 'completed' => false],
                ['id' => 'step_2', 'text' => 'Check all citations are formatted correctly', 'completed' => false],
                ['id' => 'step_3', 'text' => 'Start writing your conclusion', 'completed' => false],
            ],
            'refinement' => [
                ['id' => 'step_0', 'text' => 'Review for clarity and coherence', 'completed' => false],
                ['id' => 'step_1', 'text' => 'Check grammar and spelling', 'completed' => false],
                ['id' => 'step_2', 'text' => 'Ensure consistent citation format', 'completed' => false],
                ['id' => 'step_3', 'text' => 'Verify all claims have evidence', 'completed' => false],
            ],
            default => [
                ['id' => 'step_0', 'text' => 'Continue developing your main points', 'completed' => false],
                ['id' => 'step_1', 'text' => 'Add citations for your claims', 'completed' => false],
                ['id' => 'step_2', 'text' => 'Use clear paragraph structure', 'completed' => false],
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
