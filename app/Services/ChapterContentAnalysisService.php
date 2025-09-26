<?php

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\Log;

class ChapterContentAnalysisService
{
    /**
     * Minimum word count required for defense questions
     */
    public const MIN_WORD_COUNT_FOR_DEFENSE = 800;

    /**
     * Get word count from chapter content using regex
     */
    public function getWordCount(string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        // Remove HTML tags if present
        $cleanContent = strip_tags($content);

        // Remove extra whitespace and normalize
        $cleanContent = trim(preg_replace('/\s+/', ' ', $cleanContent));

        if (empty($cleanContent)) {
            return 0;
        }

        // Count words using regex - matches sequences of word characters
        preg_match_all('/\b\w+\b/u', $cleanContent, $matches);

        return count($matches[0]);
    }

    /**
     * Get word count from a Chapter model
     */
    public function getChapterWordCount(Chapter $chapter): int
    {
        return $this->getWordCount($chapter->content ?? '');
    }

    /**
     * Check if chapter has minimum word count for defense questions
     */
    public function hasMinimumWordCountForDefense(Chapter $chapter): bool
    {
        $wordCount = $this->getChapterWordCount($chapter);

        Log::info('Chapter word count check', [
            'chapter_id' => $chapter->id,
            'chapter_number' => $chapter->chapter_number,
            'word_count' => $wordCount,
            'minimum_required' => self::MIN_WORD_COUNT_FOR_DEFENSE,
            'meets_requirement' => $wordCount >= self::MIN_WORD_COUNT_FOR_DEFENSE,
        ]);

        return $wordCount >= self::MIN_WORD_COUNT_FOR_DEFENSE;
    }

    /**
     * Get detailed content analysis for a chapter
     */
    public function analyzeChapterContent(Chapter $chapter): array
    {
        $content = $chapter->content ?? '';
        $wordCount = $this->getWordCount($content);

        return [
            'word_count' => $wordCount,
            'character_count' => strlen($content),
            'character_count_no_spaces' => strlen(preg_replace('/\s/', '', $content)),
            'paragraph_count' => $this->getParagraphCount($content),
            'sentence_count' => $this->getSentenceCount($content),
            'meets_defense_requirement' => $wordCount >= self::MIN_WORD_COUNT_FOR_DEFENSE,
            'completion_percentage' => $this->getCompletionPercentage($wordCount, $chapter->target_word_count ?? 3000),
            'reading_time_minutes' => $this->estimateReadingTime($wordCount),
        ];
    }

    /**
     * Get paragraph count using regex
     */
    public function getParagraphCount(string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        // Remove HTML tags and normalize line breaks
        $cleanContent = strip_tags($content);
        $cleanContent = preg_replace('/\r\n|\r/', "\n", $cleanContent);

        // Split by double line breaks (paragraphs) and filter out empty ones
        $paragraphs = array_filter(
            preg_split('/\n\s*\n/', trim($cleanContent)),
            fn ($p) => ! empty(trim($p))
        );

        return count($paragraphs);
    }

    /**
     * Get sentence count using regex
     */
    public function getSentenceCount(string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        // Remove HTML tags
        $cleanContent = strip_tags($content);

        // Match sentences ending with ., !, or ?
        preg_match_all('/[.!?]+/', $cleanContent, $matches);

        return count($matches[0]);
    }

    /**
     * Calculate completion percentage based on target word count
     */
    public function getCompletionPercentage(int $currentWords, int $targetWords): float
    {
        if ($targetWords <= 0) {
            return 0.0;
        }

        return min(100.0, ($currentWords / $targetWords) * 100);
    }

    /**
     * Estimate reading time in minutes (average 200 words per minute)
     */
    public function estimateReadingTime(int $wordCount): float
    {
        return round($wordCount / 200, 1);
    }

    /**
     * Extract key phrases from content (simple implementation)
     */
    public function extractKeyPhrases(string $content, int $limit = 10): array
    {
        if (empty($content)) {
            return [];
        }

        // Remove HTML tags and normalize
        $cleanContent = strip_tags($content);
        $cleanContent = strtolower($cleanContent);

        // Remove common words
        $stopWords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must', 'shall',
            'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
            'my', 'your', 'his', 'her', 'its', 'our', 'their', 'me', 'him', 'her', 'us', 'them',
        ];

        // Extract words
        preg_match_all('/\b\w{4,}\b/', $cleanContent, $matches);
        $words = $matches[0];

        // Filter out stop words
        $words = array_filter($words, fn ($word) => ! in_array($word, $stopWords));

        // Count occurrences
        $wordCounts = array_count_values($words);

        // Sort by frequency
        arsort($wordCounts);

        // Return top phrases
        return array_slice(array_keys($wordCounts), 0, $limit);
    }

    /**
     * Check if content has substantial academic content
     */
    public function hasSubstantialContent(Chapter $chapter): bool
    {
        $analysis = $this->analyzeChapterContent($chapter);

        return $analysis['word_count'] >= 500 &&
               $analysis['sentence_count'] >= 10 &&
               $analysis['paragraph_count'] >= 3;
    }

    /**
     * Get content quality score (0-100)
     */
    public function getContentQualityScore(Chapter $chapter): float
    {
        $analysis = $this->analyzeChapterContent($chapter);
        $score = 0;

        // Word count score (40% of total)
        $wordScore = min(40, ($analysis['word_count'] / 2000) * 40);
        $score += $wordScore;

        // Paragraph structure score (20% of total)
        $paragraphScore = min(20, ($analysis['paragraph_count'] / 10) * 20);
        $score += $paragraphScore;

        // Sentence variety score (20% of total)
        $avgSentenceLength = $analysis['sentence_count'] > 0
            ? $analysis['word_count'] / $analysis['sentence_count']
            : 0;

        // Good sentence length is 15-25 words
        if ($avgSentenceLength >= 10 && $avgSentenceLength <= 30) {
            $score += 20;
        } elseif ($avgSentenceLength >= 5 && $avgSentenceLength <= 50) {
            $score += 10;
        }

        // Completion score (20% of total)
        $score += min(20, ($analysis['completion_percentage'] / 100) * 20);

        return round($score, 1);
    }
}
