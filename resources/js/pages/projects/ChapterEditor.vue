<!-- /resources/js/pages/projects/ChapterEditor.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, Brain, CheckCircle, Eye, Maximize2, Menu, MessageSquare, PenTool, Save, Target, BookCheck, PanelLeftClose, PanelLeftOpen, PanelRightClose, PanelRightOpen, Minimize2, Moon, Sun, Edit2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import PurchaseModal from '@/components/PurchaseModal.vue';
import { useWordBalance } from '@/composables/useWordBalance';
import { useChapterUiState } from '@/composables/useChapterUiState';
import { useChapterGeneration } from '@/composables/useChapterGeneration';
import { useChapterNavigation } from '@/composables/useChapterNavigation';
import { useChapterDefense } from '@/composables/useChapterDefense';
import type { ChapterEditorProps } from '@/types/chapter-editor';

// Import extracted components with lazy loading for performance
import WritingStatistics from '@/components/chapter-editor/WritingStatistics.vue';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import ExportMenu from '@/components/chapter-editor/ExportMenu.vue';
import { defineAsyncComponent } from 'vue';

// Lazy load heavy components to improve navigation performance
const AISidebar = defineAsyncComponent(() => import('@/components/chapter-editor/AISidebar.vue'));
const ChapterNavigation = defineAsyncComponent(() => import('@/components/chapter-editor/ChapterNavigation.vue'));
const MobileNavOverlay = defineAsyncComponent(() => import('@/components/chapter-editor/MobileNavOverlay.vue'));
const ChatModeLayout = defineAsyncComponent(() => import('@/components/chapter-editor/ChatModeLayout.vue'));
const CitationVerificationLayout = defineAsyncComponent(() => import('@/components/chapter-editor/CitationVerificationLayout.vue'));
const DefensePreparationPanel = defineAsyncComponent(() => import('@/components/chapter-editor/DefensePreparationPanel.vue'));
const DataCollectionPanel = defineAsyncComponent(() => import('@/components/chapter-editor/DataCollectionPanel.vue'));

// Import composables
import { useAutoSave } from '@/composables/useAutoSave';
import { useTextHistory } from '@/composables/useTextHistory';
import { useWritingStats } from '@/composables/useWritingStats';
import { useChapterWordCount } from '@/composables/useChapterWordCount';
import { useChapterAnalysis } from '@/composables/useChapterAnalysis';
// Streaming generation composable available for future use
// import { useStreamingGeneration, type StreamProgress } from '@/composables/useStreamingGeneration';


const props = defineProps<ChapterEditorProps>();

const richTextEditor = ref<{ editor?: any } | null>(null);
const richTextEditorFullscreen = ref<{ editor?: any } | null>(null);

const {
    chapterTitle,
    chapterContent,
    showPreview,
    isEditorDark,
    initChapterTheme,
    toggleChapterTheme,
    isNativeFullscreen,
    showAISidebar,
    showStatistics,
    activeTab,
    selectedText,
    cursorPosition,
    showChatMode,
    showCitationMode,
    showDefensePrep,
    loadChatModeFromStorage,
    saveChatModeToStorage,
    showLeftSidebar,
    showRightSidebar,
    isMobile,
    isLeftSidebarCollapsed,
    isRightSidebarCollapsed,
    showLeftSidebarInFullscreen,
    showRightSidebarInFullscreen,
} = useChapterUiState(props);

// Word balance guard
const {
    balance,
    showPurchaseModal,
    requiredWordsForModal,
    actionDescriptionForModal,
    checkAndPrompt,
    closePurchaseModal,
    estimates,
} = useWordBalance();

const ensureBalance = (requiredWords: number, action: string): boolean => {
    return checkAndPrompt(Math.max(1, Math.round(requiredWords)), action);
};

// Initialize composables
const { hasUnsavedChanges, isSaving, triggerAutoSave, save, clearAutoSave } = useAutoSave({
    delay: 10000,
    onSave: saveChapter,
});

// Toast debouncing
let lastToastTime = 0;
const TOAST_DEBOUNCE_MS = 1000;

const showSaveToast = () => {
    const now = Date.now();
    if (now - lastToastTime > TOAST_DEBOUNCE_MS) {
        lastToastTime = now;
        toast.success('Saved', {
            duration: 2000,
        });
    }
};


const { contentHistory, historyIndex, addToHistory, undo, redo, canUndo, canRedo } = useTextHistory(props.chapter.content || '');

// Legacy writing stats (keeping quality score)
const { writingStats, writingQualityScore, calculateWritingStats } = useWritingStats(chapterContent);

// Chapter analysis for academic quality assessment
const {
    isAnalyzing,
    latestAnalysis,
    analyzeChapter,
    getLatestAnalysis,
    topImprovementAreas,
    qualityLevelColor
} = useChapterAnalysis(props.chapter.id);

// Target word count computed property (defined before useChapterWordCount)
const targetWordCount = computed(() => {
    // 1) Faculty structure target (most authoritative)
    if (props.facultyChapters && props.facultyChapters.length > 0) {
        const facultyChapter = props.facultyChapters.find(ch =>
            ch.number === props.chapter.chapter_number
        );
        if (facultyChapter?.word_count) {
            return facultyChapter.word_count;
        }
    }

    // 2) Project outline target
    // Try to get target from structured outline first
    if (props.project?.outlines && props.project.outlines.length > 0) {
        const chapterOutline = props.project.outlines.find(outline =>
            outline.chapter_number === props.chapter.chapter_number
        );
        if (chapterOutline?.target_word_count) {
            return chapterOutline.target_word_count;
        }
    }

    // 3) Conservative fallback when no structured targets exist
    const baseCount = props.project.type === 'undergraduate' ? 2500 : 3500;
    return props.chapter.chapter_number === 1 || props.chapter.chapter_number === 5 ? Math.round(baseCount * 0.8) : baseCount;
});

const { generateNextChapter } = useChapterNavigation({
    props,
    allChapters: props.allChapters,
    targetWordCount,
    estimates,
    ensureBalance,
    save,
});

// New centralized word count composable (after targetWordCount is defined)
const {
    currentWordCount,
    progressPercentage: wordCountProgressPercentage,
    isComplete: isWordCountComplete,
    wordsRemaining,
    wordsToComplete,
    wordCountStats,
    wordCountProgress,
    meetsDefenseThreshold: wordCountMeetsDefenseThreshold,
    defenseWordsRemaining: wordCountDefenseWordsRemaining,
    defenseProgressPercentage: wordCountDefenseProgressPercentage,
    formatWordCount,
    getProgressDisplay,
    getCompletionDisplay,
    getWordCountStatus,
    getStatusColor,
    DEFENSE_THRESHOLD,
    countWords,
} = useChapterWordCount(chapterContent, targetWordCount);

// Use progress percentage from centralized word count composable
const progressPercentage = wordCountProgressPercentage;

const {
    defenseQuestions,
    isLoadingDefenseQuestions,
    isGeneratingDefenseQuestions,
    autoGenerateDefense,
    shouldLoadDefenseQuestions,
    loadDefenseQuestions,
    generateNewDefenseQuestions,
    markQuestionHelpful,
    hideQuestion,
    handleDefenseAutoToggle,
    getDefenseStatusMessage,
    shouldShowDefenseProgress,
    defenseProgressPercentage,
    defenseWordsRemaining,
    hasTriggeredGeneration,
    meetsDefenseThreshold,
} = useChapterDefense({
    props,
    chapterContent,
    currentWordCount,
    ensureBalance,
    estimates,
});

const {
    isGenerating,
    generationProgress,
    generationPercentage,
    generationPhase,
    estimatedTotalWords,
    streamWordCount,
    streamBuffer,
    lastStreamUpdate,
    originalContentForAppend,
    aiSuggestions,
    isLoadingSuggestions,
    showCitationHelper,
    showPresentationMode,
    isStreamingMode,
    showRecoveryDialog,
    partialContentSaved,
    savedWordCountOnError,
    isCollectingPapers,
    paperCollectionProgress,
    paperCollectionPhase,
    collectedPapersCount,
    paperCollectionPercentage,
    currentSource,
    sourcesCompleted,
    papersPreview,
    paperCollectionData,
    paperCollectionInterval,
    reconnectAttempts,
    reconnectDelay,
    isReconnecting,
    currentGenerationId,
    currentGenerationType,
    eventSource,
    editorScrollRef,
    attachScroller,
    smoothScrollToBottom,
    forceScrollToBottom,
    resetScroller,
    isAutoScrollActive,
    isUserScrollingScroller,
    scrollToBottom,
    checkConnectionQuality,
    handleAIGeneration,
    getAISuggestions,
    checkCitations,
    insertCitation,
    resumeGeneration,
    dismissRecovery,
    checkForAutoGeneration,
} = useChapterGeneration({
    props,
    chapterContent,
    targetWordCount,
    estimates,
    ensureBalance,
    save,
    triggerAutoSave,
    calculateWritingStats,
    countWords,
    selectedText,
    richTextEditor,
    richTextEditorFullscreen,
    isNativeFullscreen,
});


const isValid = computed(() => {
    const title = chapterTitle.value?.trim();
    const content = chapterContent.value?.trim();
    return title && title.length > 0 && content && content.length > 50;
});

// Mode detection (available for future use)
// const isWriteMode = computed(() => props.mode === 'write');
// const isEditMode = computed(() => props.mode === 'edit');

// Memoized props for child components to reduce re-renders
const memoizedProject = computed(() => props.project);
const memoizedChapter = computed(() => props.chapter);
const memoizedAllChapters = computed(() => props.allChapters);
const currentChapter = computed(() => props.chapter);

// Auto-save functionality
async function saveChapter(autoSave = false) {
    try {
        const response = await fetch(route('chapters.save', { project: props.project.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                project_id: props.project.id,
                chapter_number: props.chapter.chapter_number,
                title: chapterTitle.value,
                content: chapterContent.value,
                auto_save: autoSave,
                statistics: writingStats.value,
                quality_score: writingQualityScore.value,
            }),
        });

        console.log('Response status:', response.status, response.statusText);
        console.log('Response headers:', response.headers);

        // Log the raw response text first
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response was:', responseText);
            throw new Error(`Server returned non-JSON response: ${response.status} ${response.statusText}`);
        }

        if (response.ok) {
            // Toast is now handled by the keyboard shortcut handler only
        }
    } catch (error) {
        if (!autoSave) {
            toast.error('❌ Save Failed', {
                description: 'Please try again or check your connection.',
            });
        }
        throw error;
    }
}

// History functions
const handleUndo = () => {
    const undoContent = undo();
    if (undoContent !== null) {
        chapterContent.value = undoContent;
        triggerAutoSave();
    }
};

const handleRedo = () => {
    const redoContent = redo();
    if (redoContent !== null) {
        chapterContent.value = redoContent;
        triggerAutoSave();
    }
};

// Navigation
const goToChapter = (chapterNumber: number) => {
    if (hasUnsavedChanges.value) {
        if (confirm('You have unsaved changes. Save before switching chapters?')) {
            save();
        }
    }

    // Use smart routing: write for new chapters, edit for existing ones
    const chapter = props.allChapters.find(c => c.chapter_number === chapterNumber);
    const hasContent = chapter?.content && chapter.content.trim() !== '';
    const routeName = hasContent ? 'chapters.edit' : 'chapters.write';

    router.visit(
        route(routeName, {
            project: props.project.slug,
            chapter: chapterNumber,
        }),
        {
            only: ['chapter', 'mode', 'allChapters'],
            preserveState: true,
            preserveScroll: false,
        }
    );
};

// Native Fullscreen API functions
const enterNativeFullscreen = async () => {
    try {
        const element = document.documentElement;
        if (element.requestFullscreen) {
            await element.requestFullscreen();
        } else if ((element as any).webkitRequestFullscreen) {
            await (element as any).webkitRequestFullscreen();
        } else if ((element as any).msRequestFullscreen) {
            await (element as any).msRequestFullscreen();
        }
    } catch (error) {
        console.error('Error entering fullscreen:', error);
        toast.error('Fullscreen Error', { description: 'Unable to enter fullscreen mode' });
    }
};

const exitNativeFullscreen = async () => {
    try {
        if (document.exitFullscreen) {
            await document.exitFullscreen();
        } else if ((document as any).webkitExitFullscreen) {
            await (document as any).webkitExitFullscreen();
        } else if ((document as any).msExitFullscreen) {
            await (document as any).msExitFullscreen();
        }
    } catch (error) {
        console.error('Error exiting fullscreen:', error);
        toast.error('Fullscreen Error', { description: 'Unable to exit fullscreen mode' });
    }
};

const toggleNativeFullscreen = async () => {
    if (isNativeFullscreen.value) {
        await exitNativeFullscreen();
    } else {
        await enterNativeFullscreen();
    }
};

const handleFullscreenChange = () => {
    const fullscreenElement = document.fullscreenElement || (document as any).webkitFullscreenElement || (document as any).msFullscreenElement;

    isNativeFullscreen.value = !!fullscreenElement;
};

// Dark mode toggle


// Chat mode toggle with persistence
const toggleChatMode = () => {
    showChatMode.value = !showChatMode.value;
    if (showChatMode.value) {
        showCitationMode.value = false; // Close citation mode when opening chat mode
    }
    saveChatModeToStorage(showChatMode.value);
};

const exitChatMode = () => {
    showChatMode.value = false;
    saveChatModeToStorage(false);
};

const toggleCitationMode = () => {
    showCitationMode.value = !showCitationMode.value;
    if (showCitationMode.value) {
        showChatMode.value = false; // Close chat mode when opening citation mode
        saveChatModeToStorage(false);
    }
};

const exitCitationMode = () => {
    showCitationMode.value = false;
};

// Toggle presentation mode
const togglePresentationMode = () => {
    showPresentationMode.value = !showPresentationMode.value;
};

const goToBulkAnalysis = () => {
    router.visit(route('projects.analysis', { project: props.project.slug }));
};

// Event handlers
const handleContentChange = () => {
    addToHistory(chapterContent.value);
    triggerAutoSave();
    calculateWritingStats();
};

const handleKeydown = (e: KeyboardEvent) => {
    // Ctrl/Cmd + S: Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        save();
        showSaveToast();
    }

    // Ctrl/Cmd + Z: Undo
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
        e.preventDefault();
        handleUndo();
    }

    // Ctrl/Cmd + Shift + Z: Redo
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'z') {
        e.preventDefault();
        handleRedo();
    }

    // F11: Native Fullscreen
    if (e.key === 'F11') {
        e.preventDefault();
        toggleNativeFullscreen();
    }
};

// Lifecycle hooks
onMounted(() => {
    // Check mobile
    const checkMobile = () => {
        isMobile.value = window.innerWidth < 1024;
    };
    checkMobile();
    window.addEventListener('resize', checkMobile);



    // Restore chat mode from localStorage
    showChatMode.value = loadChatModeFromStorage();

    // Add keyboard navigation
    document.addEventListener('keydown', handleChapterKeyboardNavigation);

    // Add keyboard listeners
    document.addEventListener('keydown', handleKeydown);

    // Add fullscreen change listeners
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('msfullscreenchange', handleFullscreenChange);

    // Initialize stats
    calculateWritingStats();

    // Check for auto-generation from URL parameters
    checkForAutoGeneration();

    // Initialize custom theme
    initChapterTheme();
});

// Keyboard navigation
const handleChapterKeyboardNavigation = (e: KeyboardEvent) => {
    // Only handle if Ctrl/Cmd is pressed and no input is focused
    if ((e.ctrlKey || e.metaKey) && !['INPUT', 'TEXTAREA', 'SELECT'].includes((e.target as Element)?.tagName)) {
        switch (e.key) {
            case 'ArrowLeft':
                e.preventDefault();
                const prevChapterNum = props.chapter.chapter_number - 1;
                const prevChapter = props.allChapters.find(ch => ch.chapter_number === prevChapterNum);
                if (prevChapter) {
                    goToChapter(prevChapterNum);
                }
                break;
            case 'ArrowRight':
                e.preventDefault();
                const nextChapterNum = props.chapter.chapter_number + 1;
                const nextChapter = props.allChapters.find(ch => ch.chapter_number === nextChapterNum);
                if (nextChapter) {
                    goToChapter(nextChapterNum);
                }
                break;
        }
    }
};

// Handle scroll chaining for sidebar scrollable areas
onMounted(() => {
    const setupScrollChaining = (element: HTMLElement) => {
        const handleWheel = (e: WheelEvent) => {
            const { scrollTop, scrollHeight, clientHeight } = element;
            const delta = e.deltaY;

            // Check if we're at the exact boundaries
            const atTop = scrollTop <= 0;
            const atBottom = scrollTop + clientHeight >= scrollHeight;

            // Only chain to main page scroll if we're at exact boundaries and trying to scroll further
            if ((delta < 0 && atTop) || (delta > 0 && atBottom)) {
                // Don't prevent default - let it bubble to parent naturally
                // This allows the scroll to chain to the main page
                return;
            }

            // If we're not at boundaries, stop propagation to keep scroll in sidebar
            e.stopPropagation();
        };

        element.addEventListener('wheel', handleWheel, { passive: true });
        return () => element.removeEventListener('wheel', handleWheel);
    };

    // Setup for all scrollable areas after next tick to ensure DOM is ready
    nextTick(() => {
        const scrollableAreas = document.querySelectorAll('[class*="overflow-y-auto"]');
        const cleanupFunctions: (() => void)[] = [];

        scrollableAreas.forEach((area) => {
            if (area instanceof HTMLElement) {
                const cleanup = setupScrollChaining(area);
                cleanupFunctions.push(cleanup);
            }
        });

        // Store cleanup functions for onUnmounted
        (window as any).__scrollChainCleanup = cleanupFunctions;
    });
});

onUnmounted(() => {
    clearAutoSave();
    if (eventSource.value) {
        eventSource.value.close();
    }
    // Streaming mode cleanup is handled by the useSmoothScroller composable
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('keydown', handleChapterKeyboardNavigation);

    // Cleanup scroll chaining event listeners
    const cleanupFunctions = (window as any).__scrollChainCleanup;
    if (cleanupFunctions && Array.isArray(cleanupFunctions)) {
        cleanupFunctions.forEach(cleanup => cleanup());
        delete (window as any).__scrollChainCleanup;
    }

    // Remove fullscreen listeners
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.removeEventListener('msfullscreenchange', handleFullscreenChange);
});

// Watch for chapter prop changes (in case of navigation between chapters)
watch(() => props.chapter, async (newChapter) => {
    chapterTitle.value = newChapter.title || '';
    chapterContent.value = newChapter.content || '';
}, { immediate: false });

const handleDefensePanelToggle = async (isOpen: boolean) => {
    showDefensePrep.value = isOpen;

    if (isOpen && shouldLoadDefenseQuestions()) {
        await loadDefenseQuestions(false, { skipGeneration: true });
    }
};

watch(chapterContent, handleContentChange);
watch(chapterTitle, triggerAutoSave);

// Mark chapter as complete
const markAsComplete = async () => {
    console.log('🔵 [MARK COMPLETE] Function called')
    console.log('🔵 [MARK COMPLETE] Current word count:', currentWordCount.value)

    try {
        // First save the chapter
        console.log('🔵 [MARK COMPLETE] Saving chapter...')
        await save(false)
        console.log('✅ [MARK COMPLETE] Chapter saved')

        // Then mark as complete (flash messages handled by watcher)
        const routeUrl = route('chapters.mark-complete', {
            project: props.project.slug,
            chapter: props.chapter.chapter_number
        })
        console.log('🔵 [MARK COMPLETE] Posting to:', routeUrl)

        router.post(routeUrl, {}, {
            onSuccess: () => {
                console.log('✅ [MARK COMPLETE] Request successful')
            },
            onError: (errors) => {
                console.error('❌ [MARK COMPLETE] Request failed:', errors)
            }
        })
    } catch (error) {
        console.error('❌ [MARK COMPLETE] Error:', error)
        toast.error('Failed to save chapter before marking complete')
    }
};

// Initialize chapter analysis on mount (only to fetch latest stored result)
onMounted(async () => {
    await getLatestAnalysis();
});
</script>
<template>
    <TooltipProvider>
        <!-- Chat Mode Layout -->
        <ChatModeLayout v-if="showChatMode" :project="memoizedProject" :chapter="memoizedChapter"
            :class="{ 'dark': isEditorDark }" :chapter-title="chapterTitle" :chapter-content="chapterContent"
            :current-word-count="currentWordCount" :target-word-count="targetWordCount"
            :progress-percentage="progressPercentage" :writing-quality-score="writingQualityScore" :is-valid="isValid"
            :is-saving="isSaving" :show-preview="showPreview" :is-generating="isGenerating"
            :generation-progress="generationProgress" :history-index="historyIndex"
            :content-history-length="contentHistory.length" :selected-text="selectedText"
            @update:chapter-title="chapterTitle = $event" @update:chapter-content="chapterContent = $event"
            @update:selected-text="selectedText = $event" @update:show-preview="showPreview = $event"
            @save="(autoSave) => saveChapter(autoSave)" @undo="handleUndo" @redo="handleRedo"
            @exit-chat-mode="exitChatMode" />

        <!-- Citation Verification Layout -->
        <CitationVerificationLayout v-else-if="showCitationMode" :project="memoizedProject" :chapter="memoizedChapter"
            :class="{ 'dark': isEditorDark }" :chapter-title="chapterTitle" :chapter-content="chapterContent"
            :current-word-count="currentWordCount" :target-word-count="targetWordCount"
            :progress-percentage="progressPercentage" @exit-citation-mode="exitCitationMode" />

        <!-- Fullscreen Layout with Sidebars -->
        <!-- Fullscreen Layout with Sidebars -->
        <div v-else-if="isNativeFullscreen"
            :class="['flex h-screen flex-col overflow-hidden bg-background dark:bg-background font-sans selection:bg-primary/20 transition-colors duration-300', { 'dark': isEditorDark }]">
            <!-- Ambient Background Effects -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-blue-500/5 blur-[120px]">
                </div>
                <div
                    class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-purple-500/5 blur-[120px]">
                </div>
            </div>

            <!-- Header -->
            <div
                class="relative z-20 flex flex-shrink-0 items-center justify-between border-b border-border/40 bg-background/60 p-4 backdrop-blur-xl supports-[backdrop-filter]:bg-background/40 transition-all duration-300">
                <div class="flex items-center gap-5">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button @click="router.visit(route('projects.show', props.project.slug))" variant="ghost"
                                size="icon"
                                class="h-10 w-10 rounded-full hover:bg-primary/10 hover:text-primary transition-all duration-300">
                                <ArrowLeft class="h-5 w-5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Back to Project</p>
                        </TooltipContent>
                    </Tooltip>

                    <div class="flex flex-col">
                        <SafeHtmlText as="h1" class="text-lg font-bold tracking-tight text-foreground/90 font-display"
                            :content="props.project.title" />
                        <div class="flex items-center gap-3 text-xs text-muted-foreground">
                            <Badge variant="outline"
                                class="h-5 px-2 rounded-full border-primary/20 bg-primary/5 text-primary font-medium">
                                Chapter {{ props.chapter.chapter_number }}
                            </Badge>
                            <span class="flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                {{ currentWordCount }} words
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Center Control Group -->
                    <div
                        class="flex items-center p-1 rounded-full border border-border/40 bg-background/50 backdrop-blur-sm shadow-sm">
                        <Button @click="showLeftSidebarInFullscreen = !showLeftSidebarInFullscreen"
                            :variant="showLeftSidebarInFullscreen ? 'secondary' : 'ghost'" size="sm"
                            class="h-8 px-4 rounded-full text-xs font-medium transition-all duration-300"
                            :class="showLeftSidebarInFullscreen ? 'bg-primary/10 text-primary hover:bg-primary/15' : 'hover:bg-muted'">
                            <Menu class="mr-2 h-3.5 w-3.5" />
                            Outline
                        </Button>

                        <div class="w-px h-4 bg-border/50 mx-1"></div>

                        <Button @click="showRightSidebarInFullscreen = !showRightSidebarInFullscreen"
                            :variant="showRightSidebarInFullscreen ? 'secondary' : 'ghost'" size="sm"
                            class="h-8 px-4 rounded-full text-xs font-medium transition-all duration-300"
                            :class="showRightSidebarInFullscreen ? 'bg-primary/10 text-primary hover:bg-primary/15' : 'hover:bg-muted'">
                            <Brain class="mr-2 h-3.5 w-3.5" />
                            Assistant
                        </Button>
                    </div>

                    <!-- Right Actions -->
                    <div class="flex items-center gap-2">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button @click="showStatistics = !showStatistics"
                                    :variant="showStatistics ? 'secondary' : 'ghost'" size="icon"
                                    class="h-9 w-9 rounded-full transition-all hover:bg-muted">
                                    <Target class="h-4.5 w-4.5 text-muted-foreground" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ showStatistics ? 'Hide' : 'Show' }} Statistics</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button @click="toggleChapterTheme" :variant="isEditorDark ? 'secondary' : 'ghost'"
                                    size="icon" class="h-9 w-9 rounded-full transition-all hover:bg-muted">
                                    <Moon v-if="isEditorDark" class="h-4.5 w-4.5 text-foreground" />
                                    <Sun v-else class="h-4.5 w-4.5 text-muted-foreground" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Toggle Dark Mode</p>
                            </TooltipContent>
                        </Tooltip>

                        <ExportMenu :project="memoizedProject" :current-chapter="memoizedChapter"
                            :all-chapters="memoizedAllChapters" size="icon" variant="ghost"
                            class="h-9 w-9 rounded-full hover:bg-muted" />
                    </div>
                </div>
            </div>

            <!-- Progress Line -->
            <div class="relative z-20 h-[2px] w-full bg-border/20">
                <div class="absolute top-0 left-0 h-full bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 transition-all duration-700 ease-out shadow-[0_0_10px_rgba(59,130,246,0.5)]"
                    :style="{ width: `${Math.min(progressPercentage, 100)}%` }">
                </div>
            </div>

            <!-- AI Generation Progress Card -->
            <div v-if="isGenerating" class="mx-3 my-2">
                <div
                    class="relative overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-purple-50 p-3 shadow-sm dark:border-blue-800 dark:from-blue-950/30 dark:to-purple-950/30">
                    <!-- Background Animation -->
                    <div
                        class="absolute inset-0 -skew-x-12 animate-pulse bg-gradient-to-r from-transparent via-white/10 to-transparent">
                    </div>

                    <!-- Header -->
                    <div class="relative z-10 mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600">
                                    <Brain class="h-3 w-3 text-white" />
                                </div>
                                <!-- Pulsing ring -->
                                <div
                                    class="absolute inset-0 h-6 w-6 animate-ping rounded-full bg-gradient-to-br from-blue-500 to-purple-600 opacity-20">
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-semibold text-blue-900 dark:text-blue-100">AI Generator</h4>
                                <p class="text-xs text-blue-700 dark:text-blue-300">{{ generationPhase }}</p>
                            </div>
                        </div>
                        <Badge variant="outline"
                            class="border-blue-300 text-xs text-blue-700 dark:border-blue-700 dark:text-blue-300">
                            {{ Math.round(generationPercentage) }}%
                        </Badge>
                    </div>

                    <!-- Progress Bar -->
                    <div class="relative z-10 mb-2">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-xs text-blue-800 dark:text-blue-200">Generation Progress</span>
                            <span class="text-xs text-blue-600 dark:text-blue-400">{{ streamWordCount }} / {{
                                estimatedTotalWords }} words</span>
                        </div>
                        <div class="relative h-2 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900/50">
                            <!-- Animated progress bar -->
                            <div class="absolute top-0 left-0 h-full rounded-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-500 ease-out"
                                :style="{ width: `${generationPercentage}%` }">
                                <!-- Shimmer effect -->
                                <div
                                    class="absolute inset-0 animate-pulse bg-gradient-to-r from-transparent via-white/30 to-transparent">
                                </div>
                            </div>
                            <!-- Progress glow -->
                            <div class="absolute top-0 left-0 h-full rounded-full bg-gradient-to-r from-blue-400 to-purple-400 opacity-50 blur-sm transition-all duration-500"
                                :style="{ width: `${generationPercentage}%` }"></div>
                        </div>
                    </div>

                    <!-- Status Message -->
                    <div class="relative z-10 flex items-center gap-2">
                        <!-- Dynamic icon based on phase -->
                        <div class="flex-shrink-0">
                            <div v-if="generationPhase === 'Initializing'"
                                class="h-2 w-2 animate-bounce rounded-full bg-blue-500"></div>
                            <div v-else-if="generationPhase === 'Connecting'" class="flex gap-0.5">
                                <div class="h-2 w-0.5 animate-pulse rounded-full bg-blue-500"></div>
                                <div class="h-2 w-0.5 animate-pulse rounded-full bg-purple-500"
                                    style="animation-delay: 0.2s"></div>
                                <div class="h-2 w-0.5 animate-pulse rounded-full bg-blue-500"
                                    style="animation-delay: 0.4s"></div>
                            </div>
                            <div v-else-if="generationPhase === 'Generating'"
                                class="h-2 w-2 animate-spin rounded-full border-2 border-blue-500 border-t-transparent">
                            </div>
                            <div v-else-if="generationPhase === 'Complete'" class="h-2 w-2 rounded-full bg-green-500">
                            </div>
                            <div v-else class="h-2 w-2 rounded-full bg-red-500"></div>
                        </div>
                        <p class="flex-1 text-xs text-blue-800 dark:text-blue-200">{{ generationProgress }}</p>
                    </div>
                </div>
            </div>

            <!-- Writing Statistics -->
            <WritingStatistics v-if="showStatistics" :show-statistics="showStatistics"
                :current-word-count="currentWordCount" :writing-stats="writingStats" :quality-analysis="latestAnalysis"
                :is-analyzing="isAnalyzing"
                class="relative z-20 mx-4 mt-2 rounded-lg border border-border/40 bg-background/60 backdrop-blur-md" />

            <!-- Main Content with Sidebars -->
            <div class="relative z-10 flex flex-1 overflow-hidden">
                <!-- Left Sidebar -->
                <Transition enter-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="-ml-72 opacity-0" enter-to-class="ml-0 opacity-100"
                    leave-active-class="transition-all duration-300 ease-in-out" leave-from-class="ml-0 opacity-100"
                    leave-to-class="-ml-72 opacity-0">
                    <div v-if="showLeftSidebarInFullscreen"
                        class="w-[320px] flex-shrink-0 border-r border-border/50 bg-background/80 backdrop-blur-xl shadow-xl z-20">
                        <div class="h-full overflow-y-auto custom-scrollbar">
                            <div class="p-4">
                                <div class="mb-3">
                                    <h2 class="text-sm font-semibold text-foreground mb-1">Table of Contents</h2>
                                    <p class="text-xs text-muted-foreground">{{ memoizedAllChapters.length }} Chapters
                                    </p>
                                </div>
                                <Suspense>
                                    <ChapterNavigation :all-chapters="memoizedAllChapters"
                                        :current-chapter="memoizedChapter" :project="memoizedProject"
                                        :outlines="project.outlines || []" :faculty-chapters="facultyChapters || []"
                                        :current-word-count="currentWordCount" :target-word-count="targetWordCount"
                                        :writing-quality-score="writingQualityScore"
                                        :chapter-content-length="chapterContent.length" @go-to-chapter="goToChapter"
                                        @generate-next-chapter="generateNextChapter" />
                                    <template #fallback>
                                        <div class="flex items-center justify-center p-8">
                                            <div
                                                class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent">
                                            </div>
                                        </div>
                                    </template>
                                </Suspense>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Main Editor Area -->
                <div class="flex min-h-0 flex-1 flex-col bg-transparent relative">
                    <!-- Editor Container - Floating Paper Style -->
                    <div class="flex-1 overflow-hidden relative">
                        <div
                            class="absolute inset-0 overflow-y-auto custom-scrollbar flex flex-col items-center py-8 px-4 sm:px-8">

                            <!-- The "Paper" -->
                            <Card
                                class="w-full max-w-[850px] min-h-[calc(100vh-180px)] flex flex-col bg-background shadow-xl shadow-black/5 ring-1 ring-black/5 dark:ring-white/10 rounded-xl overflow-hidden transition-all duration-300">
                                <CardHeader
                                    class="flex-shrink-0 border-b border-border/30 px-8 py-6 bg-background/50 backdrop-blur-sm sticky top-0 z-10">
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-1">
                                            <Label for="chapter-title-fs"
                                                class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Chapter
                                                Title</Label>
                                            <Input id="chapter-title-fs" v-model="chapterTitle"
                                                placeholder="Enter chapter title..."
                                                class="h-auto p-0 border-0 bg-transparent text-2xl font-bold placeholder:text-muted-foreground/40 focus-visible:ring-0 px-0 text-foreground" />
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <Badge :variant="chapter.status === 'approved' ? 'default' : 'secondary'"
                                                class="rounded-full px-3">
                                                {{ chapter.status.replace('_', ' ') }}
                                            </Badge>
                                            <Badge variant="outline" class="rounded-full px-3 transition-colors" :class="{
                                                'text-green-600 border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800': (latestAnalysis?.total_score || 0) >= 80,
                                                'text-yellow-600 border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800': (latestAnalysis?.total_score || 0) >= 70 && (latestAnalysis?.total_score || 0) < 80,
                                                'text-orange-600 border-orange-200 bg-orange-50 dark:bg-orange-900/20 dark:border-orange-800': (latestAnalysis?.total_score || 0) >= 60 && (latestAnalysis?.total_score || 0) < 70,
                                                'text-red-600 border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800': (latestAnalysis?.total_score || 0) < 60
                                            }">
                                                {{ latestAnalysis?.total_score ? Math.round(latestAnalysis.total_score)
                                                    :
                                                    writingQualityScore }}% Quality
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent class="flex min-h-0 flex-1 flex-col p-0">
                                    <!-- Toolbar Area (Will be enhanced in next step) -->
                                    <div
                                        class="flex items-center justify-between px-6 py-2 border-b border-border/30 bg-muted/5">
                                        <div class="flex items-center gap-2">
                                            <Button @click="togglePresentationMode"
                                                :variant="showPresentationMode ? 'default' : 'ghost'" size="sm"
                                                class="h-7 text-xs rounded-full text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                                                <Eye class="mr-1.5 h-3.5 w-3.5" />
                                                {{ showPresentationMode ? 'Edit' : 'Preview' }}
                                            </Button>
                                        </div>
                                        <div class="space-y-1 relative z-10">
                                            <div class="text-sm font-medium text-foreground">
                                                {{ aiChapterAnalysis?.section.name || getSectionInfo(nextSection).name
                                                }}
                                            </div>
                                            <div class="text-[10px] text-muted-foreground line-clamp-2 leading-relaxed">
                                                {{ aiChapterAnalysis?.section.description ||
                                                    getSectionInfo(nextSection).description }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Editor Content -->
                                    <div class="flex-1 relative bg-background">
                                        <RichTextEditor v-show="!showPresentationMode" v-model="chapterContent"
                                            placeholder="Start writing your chapter..." min-height="500px"
                                            class="min-h-[500px] px-8 py-6" ref="richTextEditor" :show-toolbar="true"
                                            :streaming-mode="isStreamingMode" :is-generating="isGenerating"
                                            :generation-progress="generationProgress"
                                            :generation-percentage="generationPercentage"
                                            :generation-phase="generationPhase"
                                            @update:selected-text="(text) => { selectedText = text; }" />

                                        <div v-show="showPresentationMode" class="px-12 py-10 min-h-[500px]">
                                            <RichTextViewer :content="chapterContent" :show-font-controls="false"
                                                class="prose-lg mx-auto"
                                                style="font-family: 'Times New Roman', serif; line-height: 1.8" />
                                        </div>
                                    </div>
                                </CardContent>

                                <!-- Floating Action Bar -->
                                <div
                                    class="sticky bottom-0 z-10 border-t border-border/30 bg-background/80 backdrop-blur-md p-4 flex items-center justify-between">
                                    <div class="text-xs text-muted-foreground">
                                        {{ isSaving ? 'Saving...' : 'All changes saved' }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button @click="save(false)" :disabled="!isValid || isSaving" size="sm"
                                            variant="ghost"
                                            class="h-8 rounded-full text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                                            <Save class="mr-2 h-3.5 w-3.5" />
                                            Save Draft
                                        </Button>

                                        <Button @click="goToBulkAnalysis" variant="outline" size="sm"
                                            class="h-8 rounded-full">
                                            <BookCheck class="mr-2 h-3.5 w-3.5" />
                                            Run Bulk Analysis
                                        </Button>

                                        <Button @click="markAsComplete" :disabled="isSaving" size="sm"
                                            class="h-8 rounded-full bg-gradient-to-r from-primary to-primary/90 shadow-sm hover:shadow-md transition-all">
                                            <CheckCircle class="mr-2 h-3.5 w-3.5" />
                                            Complete
                                        </Button>
                                    </div>
                                </div>
                            </Card>

                            <!-- Bottom Spacer -->
                            <div class="h-12"></div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <Transition enter-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="-mr-72 opacity-0" enter-to-class="mr-0 opacity-100"
                    leave-active-class="transition-all duration-300 ease-in-out" leave-from-class="mr-0 opacity-100"
                    leave-to-class="-mr-72 opacity-0">
                    <div v-if="showRightSidebarInFullscreen"
                        class="w-72 flex-shrink-0 border-l border-border/40 bg-background/40 backdrop-blur-md">
                        <div class="h-full overflow-y-auto custom-scrollbar">
                            <div class="space-y-6 p-4">
                                <Suspense>
                                    <AISidebar :project="memoizedProject" :chapter="memoizedChapter"
                                        :is-generating="isGenerating" :selected-text="selectedText"
                                        :is-loading-suggestions="isLoadingSuggestions"
                                        :show-citation-helper="showCitationHelper" :chapter-content="chapterContent"
                                        :current-word-count="currentWordCount" :target-word-count="targetWordCount"
                                        @start-streaming-generation="handleAIGeneration"
                                        @get-ai-suggestions="getAISuggestions"
                                        @update:show-citation-helper="showCitationHelper = $event"
                                        @insert-citation="insertCitation" @check-citations="checkCitations" />
                                    <template #fallback>
                                        <div class="flex items-center justify-center p-8">
                                            <div
                                                class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent">
                                            </div>
                                        </div>
                                    </template>
                                </Suspense>

                                <Suspense>
                                    <DefensePreparationPanel :show-defense-prep="showDefensePrep"
                                        :questions="defenseQuestions" :is-loading="isLoadingDefenseQuestions"
                                        :is-generating="isGeneratingDefenseQuestions" :chapter-context="{
                                            chapter_number: currentChapter.chapter_number,
                                            chapter_title: currentChapter.title,
                                            word_count: currentWordCount
                                        }" :defense-watcher="{
                                            meetsThreshold: meetsDefenseThreshold,
                                            shouldShowProgress: shouldShowDefenseProgress,
                                            progressPercentage: defenseProgressPercentage,
                                            wordsRemaining: defenseWordsRemaining,
                                            hasTriggeredGeneration,
                                            threshold: DEFENSE_THRESHOLD,
                                            statusMessage: getDefenseStatusMessage()
                                        }" :auto-generate-enabled="autoGenerateDefense"
                                        @update:show-defense-prep="handleDefensePanelToggle"
                                        @generate-more="generateNewDefenseQuestions"
                                        @toggle-auto-generate="handleDefenseAutoToggle"
                                        @refresh="() => loadDefenseQuestions(true, { skipGeneration: true })"
                                        @mark-helpful="markQuestionHelpful" @hide-question="hideQuestion" />
                                    <template #fallback>
                                        <div class="flex items-center justify-center p-4">
                                            <div
                                                class="h-4 w-4 animate-spin rounded-full border-2 border-muted border-t-transparent">
                                            </div>
                                        </div>
                                    </template>
                                </Suspense>

                                <!-- Data Collection Panel -->
                                <Suspense>
                                    <DataCollectionPanel :chapter-id="currentChapter.id" :content="chapterContent" />
                                    <template #fallback>
                                        <div class="flex items-center justify-center p-4">
                                            <div
                                                class="h-4 w-4 animate-spin rounded-full border-2 border-muted border-t-transparent">
                                            </div>
                                        </div>
                                    </template>
                                </Suspense>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Normal mode - render inside AppLayout -->
        <AppLayout v-else
            :class="['h-screen overflow-hidden bg-background dark:bg-background transition-colors duration-300', { 'dark': isEditorDark }]">
            <div class="flex h-full w-full overflow-hidden relative">
                <!-- Ambient Background Effects -->
                <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                    <div
                        class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-blue-500/5 blur-[100px]">
                    </div>
                    <div
                        class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] rounded-full bg-purple-500/5 blur-[100px]">
                    </div>
                </div>

                <!-- Left Sidebar (Navigation) -->
                <aside v-show="!isLeftSidebarCollapsed"
                    class="hidden lg:flex w-80 flex-col border-r border-border/40 bg-background/60 backdrop-blur-xl z-20 transition-all duration-300">
                    <div class="h-14 flex items-center justify-between px-4 border-b border-border/40 shrink-0">
                        <span class="text-sm font-semibold tracking-tight text-foreground">Navigation</span>
                        <Badge variant="outline" class="text-[10px] h-5 px-1.5">{{ memoizedAllChapters.length }}
                            Chapters
                        </Badge>
                    </div>
                    <ScrollArea class="flex-1">
                        <div class="p-4">
                            <ChapterNavigation :all-chapters="memoizedAllChapters" :current-chapter="memoizedChapter"
                                :project="memoizedProject" :outlines="project.outlines || []"
                                :faculty-chapters="facultyChapters || []" :current-word-count="currentWordCount"
                                :target-word-count="targetWordCount" :writing-quality-score="writingQualityScore"
                                :chapter-content-length="chapterContent.length" @go-to-chapter="goToChapter"
                                @generate-next-chapter="generateNextChapter" />
                        </div>
                    </ScrollArea>
                </aside>

                <!-- Center Workspace -->
                <main
                    class="flex-1 flex flex-col relative z-10 min-w-0 bg-background/30 dark:bg-background transition-all duration-300">

                    <!-- Header -->
                    <header
                        class="min-h-[5.5rem] flex flex-shrink-0 items-center justify-between border-b border-border/40 bg-background/60 backdrop-blur-md px-4 py-2 relative z-30 transition-all duration-300">

                        <!-- Left: Toggles and Content -->
                        <div class="flex items-center gap-4 flex-1">
                            <!-- Sidebar Toggles -->
                            <div class="flex items-center gap-2 flex-shrink-0 h-full self-start mt-1">
                                <!-- Back Button -->
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="icon"
                                            class="h-8 w-8 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
                                            @click="router.visit(route('projects.writing', props.project.slug))">
                                            <ArrowLeft class="w-4 h-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Back to Writing</TooltipContent>
                                </Tooltip>
                                <div class="h-8 w-px bg-border/50 mx-1"></div>

                                <Button variant="ghost" size="icon"
                                    @click="isLeftSidebarCollapsed = !isLeftSidebarCollapsed"
                                    class="hidden lg:flex text-muted-foreground hover:text-foreground -ml-2 h-8 w-8">
                                    <PanelLeftClose v-if="!isLeftSidebarCollapsed" class="h-4 w-4" />
                                    <PanelLeftOpen v-else class="h-4 w-4" />
                                </Button>

                                <!-- Mobile Menu Trigger -->
                                <Button variant="ghost" size="icon" @click="showLeftSidebar = true"
                                    class="lg:hidden text-muted-foreground hover:text-foreground -ml-2 h-8 w-8">
                                    <Menu class="h-4 w-4" />
                                </Button>

                                <div class="h-8 w-px bg-border/50 hidden lg:block mx-1"></div>
                            </div>

                            <!-- Main Header Content (Column) -->
                            <div class="flex flex-col gap-1.5 flex-1 min-w-0">
                                <!-- Top Row: Project Title -->
                                <div class="flex items-start gap-3 w-full">
                                    <SafeHtmlText as="h1"
                                        class="text-lg font-bold tracking-tight text-foreground leading-tight cursor-pointer hover:text-primary/80 transition-colors break-words line-clamp-2"
                                        :content="props.project.title"
                                        @click="router.visit(route('projects.show', props.project.slug))" />
                                    <Badge variant="secondary"
                                        class="h-5 px-2 text-[10px] font-medium rounded-full bg-secondary/50 border border-border/50 shrink-0 mt-0.5">
                                        Ch {{ props.chapter.chapter_number }}
                                    </Badge>
                                </div>

                                <!-- Bottom Row: Toolbar/Buttons -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- Helper Actions -->
                                    <div
                                        class="flex items-center bg-background/50 rounded-md border border-border/50 p-0.5 mr-2 backdrop-blur-sm hidden sm:flex">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="showStatistics = !showStatistics"
                                                    :variant="showStatistics ? 'secondary' : 'ghost'" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                                                    <Target class="h-3.5 w-3.5" />
                                                    Stats
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Toggle Writing Statistics</TooltipContent>
                                        </Tooltip>
                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="togglePresentationMode"
                                                    :variant="showPresentationMode ? 'secondary' : 'ghost'" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                                                    <Eye v-if="!showPresentationMode" class="h-3.5 w-3.5" />
                                                    <Edit2 v-else class="h-3.5 w-3.5" />
                                                    {{ showPresentationMode ? 'Edit' : 'Preview' }}
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Toggle Preview Mode</TooltipContent>
                                        </Tooltip>
                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="toggleChatMode" variant="ghost" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-muted-foreground hover:text-foreground">
                                                    <MessageSquare class="h-3.5 w-3.5 text-blue-500" />
                                                    <span class="text-foreground">AI Chat</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Open AI Assistant Overlay</TooltipContent>
                                        </Tooltip>
                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="toggleNativeFullscreen" variant="ghost" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
                                                    :title="isNativeFullscreen ? 'Exit Full Screen' : 'Enter Full Screen'">
                                                    <Minimize2 v-if="isNativeFullscreen" class="h-3.5 w-3.5" />
                                                    <Maximize2 v-else class="h-3.5 w-3.5" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Toggle Full Screen</TooltipContent>
                                        </Tooltip>
                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="toggleChapterTheme" variant="ghost" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground">
                                                    <Moon v-if="isEditorDark" class="h-3.5 w-3.5 text-foreground" />
                                                    <Sun v-else class="h-3.5 w-3.5" />
                                                    {{ isEditorDark ? 'Dark' : 'Light' }}
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Toggle Dark Mode</TooltipContent>
                                        </Tooltip>
                                    </div>

                                    <ExportMenu :project="memoizedProject" :current-chapter="memoizedChapter"
                                        :all-chapters="memoizedAllChapters" size="sm" variant="outline"
                                        class="h-7 hidden md:flex text-zinc-700 dark:text-zinc-300" />

                                    <Button variant="ghost" size="sm"
                                        class="hidden md:flex h-7 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
                                        @click="goToBulkAnalysis" title="Open bulk analysis">
                                        <Brain class="w-4 h-4" />
                                        <span class="text-xs font-medium">Analyze</span>
                                    </Button>

                                    <Button @click="save(false)" :disabled="isSaving"
                                        :variant="isValid ? 'default' : 'secondary'" size="sm"
                                        class="h-7 px-3 text-xs shadow-sm gap-2">
                                        <Save class="h-3.5 w-3.5" />
                                        {{ isSaving ? 'Saving...' : 'Save' }}
                                    </Button>

                                    <Button @click="markAsComplete" :disabled="isSaving" size="sm"
                                        class="h-7 px-3 text-xs shadow-sm gap-2">
                                        <CheckCircle class="h-3.5 w-3.5" />
                                        Mark Complete
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Sidebar Toggle (Far Right) -->
                        <div class="flex items-center self-start mt-1 ml-2">
                            <Button variant="ghost" size="icon"
                                @click="isRightSidebarCollapsed = !isRightSidebarCollapsed"
                                class="hidden lg:flex text-muted-foreground hover:text-foreground ml-1 h-8 w-8">
                                <PanelRightClose v-if="!isRightSidebarCollapsed" class="h-4 w-4" />
                                <PanelRightOpen v-else class="h-4 w-4" />
                            </Button>

                            <!-- Mobile AI Trigger -->
                            <Button variant="ghost" size="icon" @click="showRightSidebar = true"
                                class="lg:hidden text-muted-foreground hover:text-foreground ml-1 h-8 w-8">
                                <Brain class="h-4 w-4" />
                            </Button>
                        </div>
                    </header>

                    <!-- Main Editor Area -->
                    <div class="flex-1 overflow-hidden relative group bg-background/20">
                        <!-- AI Status Overlay -->
                        <Transition enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 -translate-y-4" enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-4">
                            <div v-if="isGenerating"
                                class="absolute top-6 left-0 right-0 z-40 px-4 flex justify-center pointer-events-none">
                                <div class="w-full max-w-lg pointer-events-auto">
                                    <div
                                        class="relative overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50/95 to-purple-50/95 p-3 shadow-lg backdrop-blur-lg dark:border-blue-800 dark:from-blue-950/90 dark:to-purple-950/90">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="relative flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600 shadow-md">
                                                <Brain class="h-4 w-4 text-white animate-pulse" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between mb-1">
                                                    <h4 class="text-xs font-semibold text-foreground">Generating
                                                        Content...</h4>
                                                    <span class="text-xs font-mono text-muted-foreground">{{
                                                        Math.round(generationPercentage) }}%</span>
                                                </div>
                                                <div
                                                    class="h-1.5 w-full bg-blue-100 dark:bg-blue-900/40 rounded-full overflow-hidden">
                                                    <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-300"
                                                        :style="{ width: `${generationPercentage}%` }"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-muted-foreground ml-11 truncate">{{
                                            generationProgress }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </Transition>

                        <ScrollArea ref="editorScrollRef" class="h-full w-full">
                            <div class="max-w-[850px] mx-auto py-12 px-6 sm:px-10 min-h-full">
                                <Transition enter-active-class="transition-all duration-300 ease-out"
                                    enter-from-class="opacity-0 -translate-y-2"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition-all duration-200 ease-in"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 -translate-y-2">
                                    <WritingStatistics v-if="showStatistics" :show-statistics="true"
                                        :current-word-count="currentWordCount" :writing-stats="writingStats"
                                        :quality-analysis="latestAnalysis" :is-analyzing="isAnalyzing" class="mb-8" />
                                </Transition>


                                <div v-show="!showPresentationMode" class="min-h-[600px] pb-32">
                                    <RichTextEditor ref="richTextEditorFullscreen" v-model="chapterContent"
                                        placeholder="Start writing your chapter..."
                                        class="prose prose-slate dark:prose-invert prose-lg max-w-none focus:outline-none min-h-[500px]"
                                        :streaming-mode="isStreamingMode" :is-generating="isGenerating"
                                        :generation-progress="generationProgress"
                                        :generation-percentage="generationPercentage"
                                        :generation-phase="generationPhase"
                                        @update:selected-text="selectedText = $event" :min-height="'500px'" />
                                </div>
                                <div v-show="showPresentationMode" class="pb-32">
                                    <RichTextViewer :content="chapterContent"
                                        class="prose prose-slate dark:prose-invert prose-lg max-w-none" />
                                </div>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Footer Info -->
                    <div
                        class="h-7 border-t border-border/30 bg-background/40 backdrop-blur-sm flex items-center justify-between px-4 text-[10px] text-muted-foreground shrink-0">
                        <div class="flex items-center gap-3">
                            <span>Words: {{ currentWordCount }} / {{ targetWordCount }}</span>
                            <span class="hidden sm:inline">Last saved: {{ isSaving ? 'Saving...' : 'Just now' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>Quality: <span
                                    :class="writingQualityScore > 70 ? 'text-green-500' : 'text-amber-500'">{{
                                        writingQualityScore }}%</span></span>
                            <span v-if="isValid" class="text-green-500 flex items-center gap-1 ml-2">
                                <CheckCircle class="h-3 w-3" /> Ready
                            </span>
                        </div>
                    </div>
                </main>

                <!-- Right Sidebar (Tools) -->
                <aside v-show="!isRightSidebarCollapsed"
                    class="hidden lg:flex w-96 flex-col border-l border-border/40 bg-background/60 backdrop-blur-xl z-20 transition-all duration-300">
                    <div class="h-14 flex items-center justify-between px-4 border-b border-border/40 shrink-0">
                        <span class="text-sm font-semibold tracking-tight text-foreground">Writing Assistant</span>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="icon" class="h-7 w-7">
                                    <BookCheck class="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Manage Citations</TooltipContent>
                        </Tooltip>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar">
                        <div class="p-4 space-y-6">
                            <AISidebar :project="memoizedProject" :chapter="memoizedChapter"
                                :is-generating="isGenerating" :selected-text="selectedText"
                                :is-loading-suggestions="isLoadingSuggestions"
                                :show-citation-helper="showCitationHelper" :chapter-content="chapterContent"
                                :current-word-count="currentWordCount" :target-word-count="targetWordCount"
                                @start-streaming-generation="handleAIGeneration" @get-ai-suggestions="getAISuggestions"
                                @update:show-citation-helper="showCitationHelper = $event"
                                @insert-citation="insertCitation" @check-citations="checkCitations" />

                            <DefensePreparationPanel :show-defense-prep="showDefensePrep" :questions="defenseQuestions"
                                :is-loading="isLoadingDefenseQuestions" :is-generating="isGeneratingDefenseQuestions"
                                :chapter-context="{
                                    chapter_number: currentChapter.chapter_number,
                                    chapter_title: currentChapter.title,
                                    word_count: currentWordCount
                                }" :defense-watcher="{
                                    meetsThreshold: meetsDefenseThreshold,
                                    shouldShowProgress: shouldShowDefenseProgress,
                                    progressPercentage: defenseProgressPercentage,
                                    wordsRemaining: defenseWordsRemaining,
                                    hasTriggeredGeneration,
                                    threshold: DEFENSE_THRESHOLD,
                                    statusMessage: getDefenseStatusMessage()
                                }" :auto-generate-enabled="autoGenerateDefense"
                                @update:show-defense-prep="handleDefensePanelToggle"
                                @generate-more="generateNewDefenseQuestions"
                                @toggle-auto-generate="handleDefenseAutoToggle"
                                @refresh="() => loadDefenseQuestions(true, { skipGeneration: true })"
                                @mark-helpful="markQuestionHelpful" @hide-question="hideQuestion" />
                        </div>
                    </div>
                </aside>

                <!-- Mobile Overlays -->
                <MobileNavOverlay :show-left-sidebar="showLeftSidebar" :show-right-sidebar="showRightSidebar"
                    :is-mobile="isMobile" :all-chapters="memoizedAllChapters" :current-chapter="memoizedChapter"
                    :project="memoizedProject" :current-word-count="currentWordCount"
                    :target-word-count="targetWordCount" :writing-quality-score="writingQualityScore"
                    :chapter-content-length="chapterContent.length" :is-generating="isGenerating"
                    :selected-text="selectedText" :is-loading-suggestions="isLoadingSuggestions"
                    :show-citation-helper="showCitationHelper" :chapter-content="chapterContent"
                    @update:show-left-sidebar="showLeftSidebar = $event"
                    @update:show-right-sidebar="showRightSidebar = $event" @go-to-chapter="goToChapter"
                    @generate-next-chapter="generateNextChapter" @start-streaming-generation="handleAIGeneration"
                    @get-ai-suggestions="getAISuggestions" @update:show-citation-helper="showCitationHelper = $event"
                    @check-citations="checkCitations" />

                <!-- Credit balance modal -->
                <PurchaseModal :open="showPurchaseModal" :current-balance="balance"
                    :required-words="requiredWordsForModal" :action="actionDescriptionForModal"
                    @update:open="(v) => showPurchaseModal = v" @close="closePurchaseModal" />

                <!-- Recovery Dialog -->
                <div v-if="showRecoveryDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <Card class="w-full max-w-md mx-4 bg-white dark:bg-slate-900 border-amber-500/50 shadow-2xl">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Generation Interrupted
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-slate-600 dark:text-slate-300 mb-4">
                                Connection lost. <span class="font-semibold text-amber-600 dark:text-amber-400">{{
                                    savedWordCountOnError }} words</span> may have been saved.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <Button @click="resumeGeneration"
                                    class="flex-1 bg-amber-600 hover:bg-amber-700 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Reload & Recover
                                </Button>
                                <Button @click="dismissRecovery" variant="outline" class="flex-1">
                                    Dismiss
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    </TooltipProvider>
</template>

<style scoped>
/* Custom scrollbar for sidebar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--border) / 0.8);
}

/* Firefox scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--border)) transparent;
}
</style>
