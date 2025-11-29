<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;

class ManualModeAssistantService
{
    public function __construct(
        private AIContentGenerator $aiGenerator
    ) {}

    /**
     * Generate chapter writing guide for new/empty chapters
     */
    public function generateChapterWritingGuide(Chapter $chapter, Project $project): string
    {
        $prompt = $this->buildWritingGuidePrompt($chapter, $project);

        try {
            return $this->aiGenerator->generate($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);
        } catch (\Exception $e) {
            return $this->getFallbackWritingGuide($chapter);
        }
    }

    /**
     * Generate citation suggestion
     */
    public function generateCitationSuggestion(Chapter $chapter, array $context): string
    {
        $prompt = $this->buildCitationSuggestionPrompt($chapter, $context);

        try {
            return $this->aiGenerator->generate($prompt, [
                'temperature' => 0.6,
                'max_tokens' => 500,
            ]);
        } catch (\Exception $e) {
            return 'Consider adding citations to support your claims. Use APA, MLA, or Harvard format as required by your institution.';
        }
    }

    /**
     * Generate data/table suggestion
     */
    public function generateDataSuggestion(Chapter $chapter, array $context): string
    {
        $prompt = $this->buildDataSuggestionPrompt($chapter, $context);

        try {
            return $this->aiGenerator->generate($prompt, [
                'temperature' => 0.6,
                'max_tokens' => 500,
            ]);
        } catch (\Exception $e) {
            return 'Consider adding tables or figures to present your data more effectively. Visual representations help readers understand complex information.';
        }
    }

    /**
     * Generate argument strengthening suggestion
     */
    public function generateArgumentSuggestion(Chapter $chapter, array $context): string
    {
        $prompt = $this->buildArgumentSuggestionPrompt($chapter, $context);

        try {
            return $this->aiGenerator->generate($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);
        } catch (\Exception $e) {
            return 'Strengthen your arguments by using more assertive language and backing claims with evidence. Replace tentative phrases with confident academic language.';
        }
    }

    /**
     * Generate evidence suggestion
     */
    public function generateEvidenceSuggestion(Chapter $chapter, array $context): string
    {
        $prompt = $this->buildEvidenceSuggestionPrompt($chapter, $context);

        try {
            return $this->aiGenerator->generate($prompt, [
                'temperature' => 0.6,
                'max_tokens' => 500,
            ]);
        } catch (\Exception $e) {
            return 'Your claims need supporting evidence. Add citations, data, examples, or expert opinions to back up your statements.';
        }
    }

    /**
     * Generate general suggestion for other issues
     */
    public function generateGeneralSuggestion(Chapter $chapter, string $issue, array $context): string
    {
        $prompt = $this->buildGeneralSuggestionPrompt($chapter, $issue, $context);

        try {
            return $this->aiGenerator->generate($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);
        } catch (\Exception $e) {
            return "Consider reviewing your content for {$issue}. Academic writing requires clear structure and comprehensive coverage of topics.";
        }
    }

    /**
     * Build writing guide prompt
     */
    private function buildWritingGuidePrompt(Chapter $chapter, Project $project): string
    {
        return <<<PROMPT
You are an academic writing assistant helping a student write Chapter {$chapter->chapter_number}: {$chapter->title}.

Project Details:
- Title: {$project->title}
- Type: {$project->projectType}
- Course: {$project->course}
- Field of Study: {$project->field_of_study}

Task: Generate a helpful writing guide for this chapter. Include:
1. What this chapter should cover (2-3 key points)
2. Suggested structure (sections/subsections)
3. Writing tips specific to this chapter type
4. Common pitfalls to avoid

Keep it concise (under 200 words) and actionable. Focus on helping the student START writing, not overwhelm them.

Format your response as HTML with proper tags (<p>, <ul>, <li>, <strong>, etc.)
PROMPT;
    }

    /**
     * Build citation suggestion prompt
     */
    private function buildCitationSuggestionPrompt(Chapter $chapter, array $context): string
    {
        $claimCount = $context['claim_count'] ?? 0;
        $citationCount = $context['citation_count'] ?? 0;

        return <<<PROMPT
You are an academic writing assistant. The student is writing Chapter {$chapter->chapter_number}: {$chapter->title}.

Context Analysis:
- Claims made: {$claimCount}
- Citations present: {$citationCount}
- Issue: Claims need more citation support

Task: Generate a brief, actionable suggestion (under 100 words) on:
1. WHY citations are needed here
2. WHAT types of sources to cite (journals, books, etc.)
3. WHERE in the text citations are most needed

Be encouraging but clear about the importance of citations in academic writing.

Format your response as HTML with proper tags.
PROMPT;
    }

    /**
     * Build data suggestion prompt
     */
    private function buildDataSuggestionPrompt(Chapter $chapter, array $context): string
    {
        $tableCount = $context['table_count'] ?? 0;

        return <<<PROMPT
You are an academic writing assistant. The student is writing Chapter {$chapter->chapter_number}: {$chapter->title}.

Context Analysis:
- Tables/figures present: {$tableCount}
- Issue: More data visualization needed

Task: Generate a brief suggestion (under 100 words) on:
1. Why tables/figures would help in this chapter
2. What kind of data to present visually
3. Tips for creating effective academic tables

Be specific and encouraging.

Format your response as HTML with proper tags.
PROMPT;
    }

    /**
     * Build argument suggestion prompt
     */
    private function buildArgumentSuggestionPrompt(Chapter $chapter, array $context): string
    {
        return <<<PROMPT
You are an academic writing assistant. The student is writing Chapter {$chapter->chapter_number}: {$chapter->title}.

Context Analysis:
- Issue: Weak or tentative language detected

Task: Generate a brief suggestion (under 100 words) on:
1. How to strengthen academic arguments
2. Examples of strong vs weak phrasing
3. Tips for confident academic writing

Be encouraging and provide specific examples.

Format your response as HTML with proper tags.
PROMPT;
    }

    /**
     * Build evidence suggestion prompt
     */
    private function buildEvidenceSuggestionPrompt(Chapter $chapter, array $context): string
    {
        return <<<PROMPT
You are an academic writing assistant. The student is writing Chapter {$chapter->chapter_number}: {$chapter->title}.

Context Analysis:
- Issue: Claims made without supporting evidence

Task: Generate a brief suggestion (under 100 words) on:
1. Why evidence is crucial for academic claims
2. What types of evidence are appropriate
3. How to integrate evidence into writing

Be clear and actionable.

Format your response as HTML with proper tags.
PROMPT;
    }

    /**
     * Build general suggestion prompt
     */
    private function buildGeneralSuggestionPrompt(Chapter $chapter, string $issue, array $context): string
    {
        return <<<PROMPT
You are an academic writing assistant. The student is writing Chapter {$chapter->chapter_number}: {$chapter->title}.

Context Analysis:
- Issue detected: {$issue}

Task: Generate a brief, helpful suggestion (under 100 words) addressing this issue.
Be specific, actionable, and encouraging.

Format your response as HTML with proper tags.
PROMPT;
    }

    /**
     * Fallback writing guide if AI fails
     */
    private function getFallbackWritingGuide(Chapter $chapter): string
    {
        return <<<HTML
<p><strong>Writing Guide for Chapter {$chapter->chapter_number}</strong></p>
<p>Start by outlining the main points you want to cover in this chapter. Then, develop each point with supporting evidence and citations.</p>
<ul>
<li>Begin with an introduction to set the context</li>
<li>Develop your main arguments with evidence</li>
<li>Use clear topic sentences for each paragraph</li>
<li>Conclude by summarizing key takeaways</li>
</ul>
<p>Remember: Academic writing should be clear, well-structured, and evidence-based.</p>
HTML;
    }
}
