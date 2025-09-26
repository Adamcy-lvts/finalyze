import { ref, watch, computed, onMounted, type Ref } from 'vue';
import { debounce } from 'lodash-es';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string;
}

interface Project {
    id: number;
    slug: string;
}

export function useDefenseQuestionWatcher(
    project: Project,
    chapter: Chapter,
    chapterContent: Ref<string>,
    generateDefenseQuestions: () => Promise<void>
) {
    // Word count threshold for defense questions
    const DEFENSE_WORD_COUNT_THRESHOLD = 800;

    // State
    const hasTriggeredGeneration = ref(false);
    const lastWordCount = ref(0);
    const isWatching = ref(false);

    // Utility function to count words (same logic as backend)
    const countWords = (content: string): number => {
        if (!content) return 0;

        // Remove HTML tags if present
        const cleanContent = content.replace(/<[^>]*>/g, '');

        // Remove extra whitespace and normalize
        const normalizedContent = cleanContent.trim().replace(/\s+/g, ' ');

        if (!normalizedContent) return 0;

        // Count words using regex - matches sequences of word characters
        const wordMatches = normalizedContent.match(/\b\w+\b/g);
        return wordMatches ? wordMatches.length : 0;
    };

    // Computed word count
    const currentWordCount = computed(() => countWords(chapterContent.value));

    // Check if content meets threshold
    const meetsThreshold = computed(() => currentWordCount.value >= DEFENSE_WORD_COUNT_THRESHOLD);

    // Get storage key for tracking generation
    const getGenerationKey = () =>
        `defense_generation_triggered_${project.id}_ch_${chapter.chapter_number}`;

    // Check if generation was already triggered for this chapter
    const checkGenerationHistory = () => {
        const key = getGenerationKey();
        const triggered = localStorage.getItem(key);
        return triggered === 'true';
    };

    // Mark generation as triggered
    const markGenerationTriggered = () => {
        const key = getGenerationKey();
        localStorage.setItem(key, 'true');
        hasTriggeredGeneration.value = true;
    };

    // Reset generation trigger (e.g., when content drops below threshold)
    const resetGenerationTrigger = () => {
        const key = getGenerationKey();
        localStorage.removeItem(key);
        hasTriggeredGeneration.value = false;
        console.log(`🔄 DEFENSE WATCHER: Reset generation trigger for Chapter ${chapter.chapter_number}`);
    };

    // Debounced function to trigger defense question generation
    const debouncedGenerationTrigger = debounce(async () => {
        if (!isWatching.value) return;

        const wordCount = currentWordCount.value;
        console.log(`📊 DEFENSE WATCHER: Word count check - Chapter ${chapter.chapter_number}: ${wordCount} words`);

        // Check if we should trigger generation
        if (wordCount >= DEFENSE_WORD_COUNT_THRESHOLD && !hasTriggeredGeneration.value) {
            console.log(`🎯 DEFENSE WATCHER: Threshold reached! Triggering automatic generation for Chapter ${chapter.chapter_number}`);
            console.log(`   Word Count: ${wordCount}/${DEFENSE_WORD_COUNT_THRESHOLD}`);

            try {
                markGenerationTriggered();
                await generateDefenseQuestions();

                console.log(`✅ DEFENSE WATCHER: Successfully generated questions for Chapter ${chapter.chapter_number}`);
            } catch (error) {
                console.error('❌ DEFENSE WATCHER: Failed to generate questions:', error);
                // Reset trigger on failure so user can try again
                resetGenerationTrigger();
            }
        }

        // Reset trigger if content drops significantly below threshold
        if (wordCount < DEFENSE_WORD_COUNT_THRESHOLD * 0.8 && hasTriggeredGeneration.value) {
            console.log(`⬇️ DEFENSE WATCHER: Content dropped below 80% of threshold, resetting trigger`);
            resetGenerationTrigger();
        }

        lastWordCount.value = wordCount;
    }, 2000); // 2 second debounce

    // Start watching for content changes
    const startWatching = () => {
        if (isWatching.value) return;

        isWatching.value = true;
        hasTriggeredGeneration.value = checkGenerationHistory();
        lastWordCount.value = currentWordCount.value;

        console.log(`👀 DEFENSE WATCHER: Started watching Chapter ${chapter.chapter_number}`);
        console.log(`   Initial word count: ${lastWordCount.value}`);
        console.log(`   Generation already triggered: ${hasTriggeredGeneration.value}`);
        console.log(`   Threshold: ${DEFENSE_WORD_COUNT_THRESHOLD} words`);

        // Watch for content changes
        const stopWatcher = watch(
            chapterContent,
            (newContent, oldContent) => {
                if (newContent !== oldContent) {
                    debouncedGenerationTrigger();
                }
            },
            { immediate: false }
        );

        return stopWatcher;
    };

    // Stop watching
    const stopWatching = () => {
        isWatching.value = false;
        debouncedGenerationTrigger.cancel();
        console.log(`🛑 DEFENSE WATCHER: Stopped watching Chapter ${chapter.chapter_number}`);
    };

    // Force check (useful for initial load)
    const forceCheck = async () => {
        console.log(`🔍 DEFENSE WATCHER: Force checking Chapter ${chapter.chapter_number}`);
        await debouncedGenerationTrigger();
        debouncedGenerationTrigger.flush(); // Execute immediately
    };

    // Check if we should show a progress indicator
    const shouldShowProgressIndicator = computed(() => {
        const wordCount = currentWordCount.value;
        return wordCount > 0 && wordCount < DEFENSE_WORD_COUNT_THRESHOLD && !hasTriggeredGeneration.value;
    });

    // Progress percentage
    const progressPercentage = computed(() => {
        const percentage = (currentWordCount.value / DEFENSE_WORD_COUNT_THRESHOLD) * 100;
        return Math.min(percentage, 100);
    });

    // Words remaining
    const wordsRemaining = computed(() => {
        const remaining = DEFENSE_WORD_COUNT_THRESHOLD - currentWordCount.value;
        return Math.max(remaining, 0);
    });

    // Get status message
    const getStatusMessage = () => {
        const wordCount = currentWordCount.value;

        if (hasTriggeredGeneration.value) {
            return `Defense questions available (${wordCount} words)`;
        }

        if (wordCount >= DEFENSE_WORD_COUNT_THRESHOLD) {
            return `Ready for defense questions (${wordCount} words)`;
        }

        if (wordCount > 0) {
            const remaining = DEFENSE_WORD_COUNT_THRESHOLD - wordCount;
            return `${remaining} more words needed for defense questions`;
        }

        return `Start writing to enable defense questions (${DEFENSE_WORD_COUNT_THRESHOLD} words needed)`;
    };

    return {
        // State
        isWatching,
        hasTriggeredGeneration,
        currentWordCount,
        lastWordCount,

        // Computed
        meetsThreshold,
        shouldShowProgressIndicator,
        progressPercentage,
        wordsRemaining,

        // Methods
        startWatching,
        stopWatching,
        forceCheck,
        resetGenerationTrigger,
        getStatusMessage,
        countWords,

        // Constants
        DEFENSE_WORD_COUNT_THRESHOLD
    };
}