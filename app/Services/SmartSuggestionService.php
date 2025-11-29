<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\ChapterContextAnalysis;
use App\Models\ChapterSuggestion;
use App\Models\UserChapterSuggestion;

class SmartSuggestionService
{
    public function __construct(
        private ManualModeAssistantService $aiAssistant
    ) {}

    /**
     * Generate suggestion for new/empty chapter
     */
    public function generateInitialGuidance(Chapter $chapter): UserChapterSuggestion
    {
        $project = $chapter->project;

        // Check for existing template
        $template = ChapterSuggestion::where([
            'project_category_id' => $project->project_category_id,
            'chapter_number' => $chapter->chapter_number,
            'course_field' => $project->course,
        ])->first();

        if ($template) {
            $suggestion = $template->suggestion_content;
            $template->increment('usage_count');
            $template->update(['last_used_at' => now()]);
        } else {
            // Generate with AI
            $suggestion = $this->aiAssistant->generateChapterWritingGuide(
                $chapter,
                $project
            );

            // Save as template
            $template = ChapterSuggestion::create([
                'project_id' => $project->id,
                'project_category_id' => $project->project_category_id,
                'chapter_number' => $chapter->chapter_number,
                'course_field' => $project->course,
                'topic_keywords' => $this->extractTopicKeywords($project->title),
                'suggestion_type' => 'writing_guide',
                'suggestion_content' => $suggestion,
                'usage_count' => 1,
                'last_used_at' => now(),
            ]);
        }

        return UserChapterSuggestion::create([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'chapter_id' => $chapter->id,
            'chapter_suggestion_id' => $template->id,
            'suggestion_type' => 'writing_guide',
            'suggestion_content' => $suggestion,
            'trigger_reason' => 'new_chapter',
            'status' => 'pending',
            'shown_at' => now(),
        ]);
    }

    /**
     * Generate suggestion based on frontend analysis
     */
    public function generateFromAnalysis(
        Chapter $chapter,
        array $frontendAnalysis
    ): ?UserChapterSuggestion {
        $project = $chapter->project;

        // Save analysis to database
        ChapterContextAnalysis::updateOrCreate(
            ['chapter_id' => $chapter->id],
            [
                'project_id' => $project->id,
                'word_count' => $frontendAnalysis['word_count'] ?? 0,
                'citation_count' => $frontendAnalysis['citation_count'] ?? 0,
                'table_count' => $frontendAnalysis['table_count'] ?? 0,
                'figure_count' => $frontendAnalysis['figure_count'] ?? 0,
                'claim_count' => $frontendAnalysis['claim_count'] ?? 0,
                'has_introduction' => $frontendAnalysis['has_introduction'] ?? false,
                'has_conclusion' => $frontendAnalysis['has_conclusion'] ?? false,
                'detected_issues' => $frontendAnalysis['detected_issues'] ?? [],
                'content_quality_metrics' => $frontendAnalysis['quality_metrics'] ?? [],
                'last_analyzed_at' => now(),
            ]
        );

        // If no issues, no suggestion needed
        if (empty($frontendAnalysis['detected_issues'])) {
            return null;
        }

        // Get highest priority issue
        $priorityIssue = $this->prioritizeIssue($frontendAnalysis['detected_issues']);

        // Generate AI suggestion
        $suggestion = $this->generateSuggestionForIssue(
            $chapter,
            $priorityIssue,
            $frontendAnalysis
        );

        // Auto-dismiss previous pending suggestion
        UserChapterSuggestion::where('chapter_id', $chapter->id)
            ->where('status', 'pending')
            ->update(['status' => 'auto_dismissed']);

        return UserChapterSuggestion::create([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'chapter_id' => $chapter->id,
            'suggestion_type' => $priorityIssue,
            'suggestion_content' => $suggestion,
            'trigger_reason' => "detected_{$priorityIssue}",
            'detected_issues' => $frontendAnalysis['detected_issues'],
            'status' => 'pending',
            'shown_at' => now(),
        ]);
    }

    private function generateSuggestionForIssue(
        Chapter $chapter,
        string $issue,
        array $analysis
    ): string {
        return match ($issue) {
            'claims_without_evidence' => $this->aiAssistant->generateEvidenceSuggestion($chapter, $analysis),
            'insufficient_citations' => $this->aiAssistant->generateCitationSuggestion($chapter, $analysis),
            'insufficient_tables' => $this->aiAssistant->generateDataSuggestion($chapter, $analysis),
            'weak_arguments' => $this->aiAssistant->generateArgumentSuggestion($chapter, $analysis),
            default => $this->aiAssistant->generateGeneralSuggestion($chapter, $issue, $analysis),
        };
    }

    private function prioritizeIssue(array $issues): string
    {
        // Priority order
        $priority = [
            'claims_without_evidence',
            'insufficient_citations',
            'weak_arguments',
            'insufficient_tables',
            'missing_methodology',
            'unclear_structure',
            'insufficient_content',
        ];

        foreach ($priority as $issue) {
            if (in_array($issue, $issues)) {
                return $issue;
            }
        }

        return $issues[0];
    }

    private function extractTopicKeywords(string $title): string
    {
        // Simple keyword extraction - remove common words
        $commonWords = ['a', 'an', 'the', 'of', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'from'];
        $words = explode(' ', strtolower($title));
        $keywords = array_filter($words, fn ($word) => ! in_array($word, $commonWords) && strlen($word) > 3);

        return implode(' ', array_slice($keywords, 0, 5));
    }
}
