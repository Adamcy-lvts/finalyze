import { computed, ref, type Ref } from 'vue';

export interface WritingStats {
    sentences: number;
    paragraphs: number;
    readingTime: number;
    avgWordLength: number;
    uniqueWords: number;
    commonWords: string[];
}

export function useWritingStats(content: Ref<string>) {
    const writingStats = ref<WritingStats>({
        sentences: 0,
        paragraphs: 0,
        readingTime: 0,
        avgWordLength: 0,
        uniqueWords: 0,
        commonWords: [],
    });

    const currentWordCount = computed(() => {
        return content.value ? content.value.split(/\s+/).filter((word) => word.length > 0).length : 0;
    });

    const calculateWritingStats = () => {
        if (!content.value) {
            writingStats.value = {
                sentences: 0,
                paragraphs: 0,
                readingTime: 0,
                avgWordLength: 0,
                uniqueWords: 0,
                commonWords: [],
            };
            return;
        }

        const contentText = content.value;
        const words = contentText.split(/\s+/).filter((w) => w.length > 0);
        const sentences = contentText.split(/[.!?]+/).filter((s) => s.trim().length > 0);
        const paragraphs = contentText.split('\n\n').filter((p) => p.trim().length > 0);

        // Calculate unique words
        const wordFrequency = new Map<string, number>();
        words.forEach((word) => {
            const cleanWord = word.toLowerCase().replace(/[^a-z0-9]/g, '');
            if (cleanWord) {
                wordFrequency.set(cleanWord, (wordFrequency.get(cleanWord) || 0) + 1);
            }
        });

        // Get most common words (excluding common stop words)
        const stopWords = new Set([
            'the',
            'a',
            'an',
            'and',
            'or',
            'but',
            'in',
            'on',
            'at',
            'to',
            'for',
            'of',
            'with',
            'by',
            'is',
            'was',
            'are',
            'were',
        ]);
        const commonWords = Array.from(wordFrequency.entries())
            .filter(([word]) => !stopWords.has(word))
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5)
            .map(([word]) => word);

        writingStats.value = {
            sentences: sentences.length,
            paragraphs: paragraphs.length,
            readingTime: Math.ceil(words.length / 200), // Average reading speed
            avgWordLength: words.reduce((acc, w) => acc + w.length, 0) / words.length,
            uniqueWords: wordFrequency.size,
            commonWords,
        };
    };

    const writingQualityScore = computed(() => {
        if (!content.value) return 0;

        let score = 0;
        const contentText = content.value;

        // Check for structure elements
        if (contentText.includes('##')) score += 20; // Has headings
        if (contentText.length > 1000) score += 20; // Substantial content
        if (contentText.match(/\[.*?\]/g)) score += 15; // Has citations
        if (contentText.includes('**') || contentText.includes('*')) score += 10; // Has formatting

        // Sentence variety
        const sentences = contentText.split(/[.!?]+/).filter((s) => s.trim().length > 0);
        if (sentences.length > 0) {
            const avgSentenceLength = sentences.reduce((acc, s) => acc + s.split(/\s+/).length, 0) / sentences.length;
            if (avgSentenceLength > 10 && avgSentenceLength < 25) score += 20; // Good sentence length
        }

        // Paragraph structure
        const paragraphs = contentText.split('\n\n').filter((p) => p.trim().length > 0);
        if (paragraphs.length > 3) score += 15; // Multiple paragraphs

        return Math.min(score, 100);
    });

    return {
        writingStats,
        currentWordCount,
        writingQualityScore,
        calculateWritingStats,
    };
}
