import { computed, ref, type Ref } from 'vue';

/**
 * Centralized composable for consistent chapter word count calculations
 * Uses the same logic as ChapterContentAnalysisService for consistency
 */

export interface WordCountStats {
    wordCount: number;
    characterCount: number;
    characterCountNoSpaces: number;
    paragraphCount: number;
    sentenceCount: number;
    readingTimeMinutes: number;
}

export interface WordCountProgress {
    current: number;
    target: number;
    percentage: number;
    isComplete: boolean;
    completionThreshold: number; // Default 80%
}

export function useChapterWordCount(
    content: Ref<string>,
    targetWordCount: Ref<number> = ref(3000),
    completionThreshold: number = 0.8
) {
    /**
     * Core word counting function - matches ChapterContentAnalysisService
     * Strips HTML tags and uses regex for accurate word counting
     */
    const countWords = (text: string): number => {
        if (!text) return 0;

        // Remove HTML tags if present
        const cleanContent = text.replace(/<[^>]*>/g, '');

        // Remove extra whitespace and normalize
        const normalizedContent = cleanContent.trim().replace(/\s+/g, ' ');

        if (!normalizedContent) return 0;

        // Count words using regex - matches sequences of word characters
        const wordMatches = normalizedContent.match(/\b\w+\b/g);
        return wordMatches ? wordMatches.length : 0;
    };

    /**
     * Count paragraphs using double line breaks
     */
    const countParagraphs = (text: string): number => {
        if (!text) return 0;

        // Remove HTML tags and normalize line breaks
        const cleanContent = text.replace(/<[^>]*>/g, '');
        const normalizedContent = cleanContent.replace(/\r\n|\r/g, '\n');

        // Split by double line breaks (paragraphs) and filter out empty ones
        const paragraphs = normalizedContent
            .trim()
            .split(/\n\s*\n/)
            .filter(p => p.trim().length > 0);

        return paragraphs.length;
    };

    /**
     * Count sentences using punctuation
     */
    const countSentences = (text: string): number => {
        if (!text) return 0;

        // Remove HTML tags
        const cleanContent = text.replace(/<[^>]*>/g, '');

        // Match sentences ending with ., !, or ?
        const sentenceMatches = cleanContent.match(/[.!?]+/g);
        return sentenceMatches ? sentenceMatches.length : 0;
    };

    /**
     * Estimate reading time (average 200 words per minute)
     */
    const estimateReadingTime = (wordCount: number): number => {
        return Math.round((wordCount / 200) * 10) / 10; // Round to 1 decimal
    };

    // Reactive word count calculation
    const currentWordCount = computed(() => countWords(content.value));

    // Character counts
    const characterCount = computed(() => content.value.length);
    const characterCountNoSpaces = computed(() => content.value.replace(/\s/g, '').length);

    // Paragraph and sentence counts
    const paragraphCount = computed(() => countParagraphs(content.value));
    const sentenceCount = computed(() => countSentences(content.value));

    // Reading time
    const readingTimeMinutes = computed(() => estimateReadingTime(currentWordCount.value));

    // Progress calculations
    const progressPercentage = computed(() => {
        const target = targetWordCount.value;
        if (!target || target <= 0) return 0;
        return Math.min((currentWordCount.value / target) * 100, 100);
    });

    const isComplete = computed(() => {
        return currentWordCount.value >= targetWordCount.value * completionThreshold;
    });

    const wordsRemaining = computed(() => {
        const remaining = targetWordCount.value - currentWordCount.value;
        return Math.max(remaining, 0);
    });

    const wordsToComplete = computed(() => {
        const needed = (targetWordCount.value * completionThreshold) - currentWordCount.value;
        return Math.max(needed, 0);
    });

    // Comprehensive stats object
    const wordCountStats = computed<WordCountStats>(() => ({
        wordCount: currentWordCount.value,
        characterCount: characterCount.value,
        characterCountNoSpaces: characterCountNoSpaces.value,
        paragraphCount: paragraphCount.value,
        sentenceCount: sentenceCount.value,
        readingTimeMinutes: readingTimeMinutes.value,
    }));

    // Progress object
    const wordCountProgress = computed<WordCountProgress>(() => ({
        current: currentWordCount.value,
        target: targetWordCount.value,
        percentage: progressPercentage.value,
        isComplete: isComplete.value,
        completionThreshold,
    }));

    // Utility methods for formatting
    const formatWordCount = (count: number): string => {
        if (count >= 1000) {
            return `${Math.round(count / 100) / 10}k`; // e.g., 1.2k, 3.4k
        }
        return count.toString();
    };

    const formatProgress = (decimals: number = 0): string => {
        return `${Math.round(progressPercentage.value * Math.pow(10, decimals)) / Math.pow(10, decimals)}%`;
    };

    const getProgressDisplay = (): string => {
        return `${currentWordCount.value} / ${targetWordCount.value} words (${formatProgress()}%)`;
    };

    const getCompletionDisplay = (): string => {
        if (isComplete.value) {
            return `✅ Complete (${formatWordCount(currentWordCount.value)} words)`;
        }
        const needed = wordsToComplete.value;
        return `${formatWordCount(needed)} more words to complete`;
    };

    // Status helpers
    const getWordCountStatus = (): 'empty' | 'started' | 'progressing' | 'near_complete' | 'complete' | 'exceeded' => {
        const count = currentWordCount.value;
        const target = targetWordCount.value;

        if (count === 0) return 'empty';
        if (count < target * 0.25) return 'started';
        if (count < target * 0.75) return 'progressing';
        if (count < target * completionThreshold) return 'near_complete';
        if (count < target) return 'complete';
        return 'exceeded';
    };

    const getStatusColor = (): string => {
        const status = getWordCountStatus();
        const colorMap = {
            empty: 'text-gray-500',
            started: 'text-blue-500',
            progressing: 'text-yellow-500',
            near_complete: 'text-orange-500',
            complete: 'text-green-500',
            exceeded: 'text-emerald-600',
        };
        return colorMap[status];
    };

    const getProgressBarColor = (): string => {
        const status = getWordCountStatus();
        const colorMap = {
            empty: 'bg-gray-200',
            started: 'bg-blue-200',
            progressing: 'bg-yellow-200',
            near_complete: 'bg-orange-200',
            complete: 'bg-green-200',
            exceeded: 'bg-emerald-200',
        };
        return colorMap[status];
    };

    // Defense question eligibility (matches ChapterContentAnalysisService threshold)
    const DEFENSE_THRESHOLD = 800;
    const meetsDefenseThreshold = computed(() => currentWordCount.value >= DEFENSE_THRESHOLD);
    const defenseWordsRemaining = computed(() => Math.max(DEFENSE_THRESHOLD - currentWordCount.value, 0));
    const defenseProgressPercentage = computed(() => Math.min((currentWordCount.value / DEFENSE_THRESHOLD) * 100, 100));

    return {
        // Core counts
        currentWordCount,
        characterCount,
        characterCountNoSpaces,
        paragraphCount,
        sentenceCount,
        readingTimeMinutes,

        // Progress
        progressPercentage,
        isComplete,
        wordsRemaining,
        wordsToComplete,

        // Computed objects
        wordCountStats,
        wordCountProgress,

        // Defense question integration
        meetsDefenseThreshold,
        defenseWordsRemaining,
        defenseProgressPercentage,
        DEFENSE_THRESHOLD,

        // Formatting helpers
        formatWordCount,
        formatProgress,
        getProgressDisplay,
        getCompletionDisplay,

        // Status helpers
        getWordCountStatus,
        getStatusColor,
        getProgressBarColor,

        // Raw calculation function (for external use)
        countWords,
    };
}