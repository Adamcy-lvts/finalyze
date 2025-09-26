import { ref, computed, watch, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';
import { smartSuggestionsService, type ScoredSuggestion, type ChapterAnalysis } from '@/services/SmartSuggestionsService';

interface UseSmartSuggestionsOptions {
    projectId: number;
    chapterId: number;
    chapterContent: string;
    selectedText?: string;
    isGenerating?: boolean;
    autoAnalyze?: boolean;
}

export function useSmartSuggestions(initialOptions: UseSmartSuggestionsOptions) {
    // Create reactive refs for the options to handle prop changes
    const options = ref(initialOptions);
    
    // Reactive state
    const suggestions = ref<ScoredSuggestion[]>([]);
    const chapterAnalysis = ref<ChapterAnalysis | null>(null);
    const isLoadingSuggestions = ref(false);
    const isLoadingAnalysis = ref(false);
    const lastAnalyzedContent = ref('');

    // Computed properties
    const hasValidChapter = computed(() => !!options.value.chapterId && options.value.chapterId > 0);
    const hasContent = computed(() => options.value.chapterContent && options.value.chapterContent.trim().length > 0);
    const shouldShowAnalyzeButton = computed(() => 
        !chapterAnalysis.value && hasValidChapter.value && !isLoadingAnalysis.value
    );
    const isEmptyChapter = computed(() => !hasContent.value && hasValidChapter.value);

    // Content metrics for debugging/monitoring
    const contentMetrics = computed(() => {
        if (!options.value.chapterContent) return null;
        return smartSuggestionsService.analyzeContent(options.value.chapterContent);
    });

    // Generate smart suggestions
    const generateSuggestions = async (force = false): Promise<void> => {
        if (!hasContent.value || isLoadingSuggestions.value || options.value.isGenerating) {
            return;
        }

        // Avoid redundant calls for same content
        if (!force && options.value.chapterContent === lastAnalyzedContent.value) {
            return;
        }

        isLoadingSuggestions.value = true;
        lastAnalyzedContent.value = options.value.chapterContent;

        try {
            const newSuggestions = await smartSuggestionsService.generateSmartSuggestions(
                options.value.chapterContent,
                options.value.selectedText || '',
                chapterAnalysis.value || undefined
            );

            suggestions.value = newSuggestions;
        } catch (error) {
            console.error('Failed to generate smart suggestions:', error);
            toast.error('Failed to generate suggestions. Please try again.');
            suggestions.value = [];
        } finally {
            isLoadingSuggestions.value = false;
        }
    };

    // Analyze chapter structure and progress
    const analyzeChapter = async (force = false): Promise<void> => {
        if (!hasValidChapter.value || (!force && isLoadingAnalysis.value)) {
            return;
        }

        // Avoid redundant analysis calls
        if (!force && chapterAnalysis.value && options.value.chapterContent === lastAnalyzedContent.value) {
            return;
        }

        isLoadingAnalysis.value = true;

        try {
            const analysis = await smartSuggestionsService.getChapterAnalysis(
                options.value.projectId,
                options.value.chapterId,
                options.value.chapterContent
            );

            chapterAnalysis.value = analysis;
            lastAnalyzedContent.value = options.value.chapterContent;

            // Auto-generate suggestions after analysis
            if (hasContent.value) {
                await generateSuggestions(true);
            }
        } catch (error) {
            console.error('Failed to analyze chapter:', error);
            toast.error('Failed to analyze chapter. Please try again.');
            chapterAnalysis.value = null;
        } finally {
            isLoadingAnalysis.value = false;
        }
    };

    // Manual refresh function
    const refreshSuggestions = async (): Promise<void> => {
        if (options.value.isGenerating) {
            toast('Please wait for current generation to complete');
            return;
        }

        // Clear cache and regenerate
        smartSuggestionsService.clearCache();
        
        if (hasContent.value) {
            await generateSuggestions(true);
        }
        
        if (hasValidChapter.value) {
            await analyzeChapter(true);
        }

        toast.success('Suggestions refreshed');
    };

    // Apply a suggestion
    const applySuggestion = (suggestion: ScoredSuggestion, emit: any): void => {
        switch (suggestion.action) {
            case 'generate-section':
                emit('startStreamingGeneration', 'section', { 
                    section: suggestion.section,
                    mode: 'progressive' 
                });
                toast(`Generating ${suggestion.title.toLowerCase()}...`);
                break;

            case 'expand':
                if (options.value.selectedText) {
                    emit('startStreamingGeneration', 'expand', { 
                        selectedText: options.value.selectedText 
                    });
                    toast('Expanding selected text...');
                } else {
                    toast.error('Please select text to expand');
                }
                break;

            case 'improve':
                emit('startStreamingGeneration', 'improve');
                toast('Improving chapter structure...');
                break;

            case 'rephrase':
                if (options.value.selectedText) {
                    emit('startStreamingGeneration', 'rephrase', { 
                        selectedText: options.value.selectedText,
                        style: suggestion.style || 'Academic Formal'
                    });
                    toast('Rephrasing selected text...');
                } else {
                    toast.error('Please select text to rephrase');
                }
                break;

            case 'cite':
                emit('update:showCitationHelper', true);
                toast('Opening citation helper...');
                break;

            default:
                console.warn(`Unknown suggestion action: ${suggestion.action}`);
                toast.error('Unknown action type');
        }
    };

    // Get cache statistics for debugging
    const getCacheStats = () => smartSuggestionsService.getCacheStats();

    // Update options (for reactive prop changes)
    const updateOptions = (newOptions: UseSmartSuggestionsOptions) => {
        options.value = newOptions;
    };

    // Get prioritized suggestions
    const getTopSuggestions = (limit = 4) => computed(() => 
        suggestions.value
            .filter(s => s.priority === 'high' || s.score > 70)
            .slice(0, limit)
    );

    const getMediumPrioritySuggestions = () => computed(() =>
        suggestions.value
            .filter(s => s.priority === 'medium' && s.score <= 70)
            .slice(0, 3)
    );

    // Reset state when chapter changes
    const resetState = (): void => {
        suggestions.value = [];
        chapterAnalysis.value = null;
        isLoadingSuggestions.value = false;
        isLoadingAnalysis.value = false;
        lastAnalyzedContent.value = '';
    };

    // Watch for chapter changes
    watch(
        () => options.value.chapterId,
        (newChapterId, oldChapterId) => {
            if (newChapterId !== oldChapterId) {
                resetState();
            }
        },
        { immediate: false }
    );

    // Watch for content changes
    watch(
        () => options.value.chapterContent,
        (newContent, oldContent) => {
            // Reset analysis when content becomes empty
            if (chapterAnalysis.value && (!newContent || newContent.trim().length === 0)) {
                chapterAnalysis.value = null;
                suggestions.value = [];
            }
            // Auto-generate suggestions when content is added to empty chapter
            else if (
                options.value.autoAnalyze !== false &&
                (!oldContent || oldContent.trim().length === 0) && 
                newContent && 
                newContent.trim().length > 100
            ) {
                setTimeout(() => generateSuggestions(), 500);
            }
        },
        { immediate: false }
    );

    // Clean up on unmount
    onUnmounted(() => {
        // Clear any pending debounced operations
        smartSuggestionsService.clearCache();
    });

    return {
        // State
        suggestions,
        chapterAnalysis,
        isLoadingSuggestions,
        isLoadingAnalysis,
        contentMetrics,

        // Computed
        hasValidChapter,
        hasContent,
        shouldShowAnalyzeButton,
        isEmptyChapter,

        // Methods
        generateSuggestions,
        analyzeChapter,
        refreshSuggestions,
        applySuggestion,
        resetState,
        getCacheStats,

        // Filtered suggestions
        getTopSuggestions,
        getMediumPrioritySuggestions,

        // Option management
        updateOptions
    };
}