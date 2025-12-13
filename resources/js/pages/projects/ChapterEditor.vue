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
// SafeHtmlText DISABLED - causes dark mode issues
// import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, Brain, CheckCircle, Eye, Maximize2, Menu, MessageSquare, PenTool, Save, Target, BookCheck, PanelLeftClose, PanelLeftOpen, PanelRightClose, PanelRightOpen, Minimize2, Moon, Sun, Edit2, Search } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import PurchaseModal from '@/components/PurchaseModal.vue';
import { useWordBalance } from '@/composables/useWordBalance';
import { useChapterUiState } from '@/composables/useChapterUiState';
import { useChapterGeneration } from '@/composables/useChapterGeneration';
import { useChapterNavigation } from '@/composables/useChapterNavigation';
import { useChapterDefense } from '@/composables/useChapterDefense';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useAppearance } from '@/composables/useAppearance';
import ChapterEditorPaperCard from '@/components/chapter-editor/ChapterEditorPaperCard.vue';
import ChapterEditorRightSidebar from '@/components/chapter-editor/ChapterEditorRightSidebar.vue';
import type { ChapterEditorPaperCardContext } from '@/types/chapter-editor-layout';
import type { ChapterEditorProps } from '@/types/chapter-editor';

// Import extracted components with lazy loading for performance
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import ExportMenu from '@/components/chapter-editor/ExportMenu.vue';
import { defineAsyncComponent } from 'vue';

// Lazy load heavy components to improve navigation performance
const AISidebar = defineAsyncComponent(() => import('@/components/chapter-editor/AISidebar.vue'));
const ChapterNavigation = defineAsyncComponent(() => import('@/components/chapter-editor/ChapterNavigation.vue'));
const MobileNavOverlay = defineAsyncComponent(() => import('@/components/chapter-editor/MobileNavOverlay.vue'));
// ChatModeLayout DISABLED - causes dark mode issues
// const ChatModeLayout = defineAsyncComponent(() => import('@/components/chapter-editor/ChatModeLayout.vue'));
const CitationVerificationLayout = defineAsyncComponent(() => import('@/components/chapter-editor/CitationVerificationLayout.vue'));
// DefensePreparationPanel DISABLED - causes dark mode issues
// const DefensePreparationPanel = defineAsyncComponent(() => import('@/components/chapter-editor/DefensePreparationPanel.vue'));
// DataCollectionPanel DISABLED in ChapterEditor

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

const isThemeSandbox = ref(false);
const isThemeSandboxNoLayout = ref(false);
const themeSandboxStep = ref(0);
if (typeof window !== 'undefined') {
    const params = new URLSearchParams(window.location.search);
    isThemeSandbox.value = params.has('theme_sandbox');
    isThemeSandboxNoLayout.value = params.get('theme_sandbox') === 'nolayout' || params.has('theme_sandbox_nolayout');
    const rawStep = params.get('theme_sandbox_step');
    const parsedStep = rawStep ? Number.parseInt(rawStep, 10) : 0;
    themeSandboxStep.value = Number.isFinite(parsedStep) ? parsedStep : 0;
}

const { appearance: globalAppearance, isDark: globalIsDark } = useAppearance();
const themeDebug = ref({
    htmlHasDark: false,
    background: '',
    foreground: '',
    card: '',
    sidebarBackground: '',
    bodyBg: '',
    insetBg: '',
    sampleBgBackground: '',
    sampleBgCard: '',
    sampleBgMuted: '',
});

const sampleBgBackgroundEl = ref<HTMLElement | null>(null);
const sampleBgCardEl = ref<HTMLElement | null>(null);
const sampleBgMutedEl = ref<HTMLElement | null>(null);

function refreshThemeDebug() {
    if (typeof window === 'undefined') return;
    const root = document.documentElement;
    const styles = window.getComputedStyle(root);
    const inset = document.querySelector('[data-slot="sidebar-inset"]') as HTMLElement | null;
    const insetBg = inset ? window.getComputedStyle(inset).backgroundColor : '';
    themeDebug.value = {
        htmlHasDark: root.classList.contains('dark'),
        background: styles.getPropertyValue('--background').trim(),
        foreground: styles.getPropertyValue('--foreground').trim(),
        card: styles.getPropertyValue('--card').trim(),
        sidebarBackground: styles.getPropertyValue('--sidebar-background').trim(),
        bodyBg: window.getComputedStyle(document.body).backgroundColor,
        insetBg,
        sampleBgBackground: sampleBgBackgroundEl.value ? window.getComputedStyle(sampleBgBackgroundEl.value).backgroundColor : '',
        sampleBgCard: sampleBgCardEl.value ? window.getComputedStyle(sampleBgCardEl.value).backgroundColor : '',
        sampleBgMuted: sampleBgMutedEl.value ? window.getComputedStyle(sampleBgMutedEl.value).backgroundColor : '',
    };
}

const {
    chapterTitle,
    chapterContent,
    showPreview,
    isEditorDark,
    initChapterTheme,
    toggleChapterTheme,
    isNativeFullscreen,
    showAISidebar,
    // showStatistics, // DISABLED in ChapterEditor
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

const paperCardContext = computed<ChapterEditorPaperCardContext>(() => ({
    chapter: props.chapter,
    chapterTitle,
    chapterContent,
    showPresentationMode,
    togglePresentationMode,
    isStreamingMode,
    isGenerating,
    generationProgress,
    generationPercentage,
    generationPhase,
    aiChapterAnalysis,
    nextSection,
    getSectionInfo,
    writingStats,
    latestAnalysis,
    isAnalyzing,
    writingQualityScore,
    isSaving,
    isValid,
    save,
    goToBulkAnalysis,
    markAsComplete,
    richTextEditor,
    richTextEditorFullscreen,
    selectedText,
    setSelectedText: (value: string) => {
        selectedText.value = value;
    },
}));

const sidebarChapterContext = computed(() => ({
    chapter_number: currentChapter.value.chapter_number,
    chapter_title: currentChapter.value.title,
    word_count: currentWordCount.value,
}));

const defenseWatcherState = computed(() => ({
    meetsThreshold: meetsDefenseThreshold.value,
    shouldShowProgress: shouldShowDefenseProgress.value,
    progressPercentage: defenseProgressPercentage.value,
    wordsRemaining: defenseWordsRemaining.value,
    hasTriggeredGeneration,
    threshold: DEFENSE_THRESHOLD,
    statusMessage: getDefenseStatusMessage(),
}));

const rightSidebarCommonProps = computed(() => ({
    project: memoizedProject.value,
    chapter: memoizedChapter.value,
    isGenerating: isGenerating.value,
    selectedText: selectedText.value,
    isLoadingSuggestions: isLoadingSuggestions.value,
    showCitationHelper: showCitationHelper.value,
    chapterContent: chapterContent.value,
    currentWordCount: currentWordCount.value,
    targetWordCount: targetWordCount.value,
    handleAIGeneration,
    getAISuggestions,
    updateCitationHelper: (value: boolean) => {
        showCitationHelper.value = value;
    },
    insertCitation,
    checkCitations,
    showDefensePrep: showDefensePrep.value,
    defenseQuestions: defenseQuestions.value ?? [],
    isLoadingDefenseQuestions: isLoadingDefenseQuestions.value,
    isGeneratingDefenseQuestions: isGeneratingDefenseQuestions.value,
    chapterContext: sidebarChapterContext.value,
    defenseWatcher: defenseWatcherState.value,
    autoGenerateDefense: autoGenerateDefense.value,
    handleDefensePanelToggle,
    generateNewDefenseQuestions,
    handleDefenseAutoToggle,
    refreshDefenseQuestions: () => loadDefenseQuestions(true, { skipGeneration: true }),
    markQuestionHelpful,
    hideQuestion,
}));

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

const getFullscreenElement = () => {
    return document.fullscreenElement
        || (document as any).webkitFullscreenElement
        || (document as any).msFullscreenElement
        || null;
};

const enterNativeFullscreen = async () => {
    try {
        const element = document.documentElement;
        if (element.requestFullscreen) {
            await element.requestFullscreen({ navigationUI: 'hide' } as any);
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
    if (getFullscreenElement()) {
        await exitNativeFullscreen();
        return;
    }

    await enterNativeFullscreen();
};

const handleFullscreenChange = () => {
    isNativeFullscreen.value = !!getFullscreenElement();

    nextTick(() => {
        initChapterTheme();
        if (isThemeSandbox.value) {
            refreshThemeDebug();
        }
    });
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

    // Ctrl/Cmd + Shift + F: Toggle native fullscreen
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'f' || e.key === 'F')) {
        e.preventDefault();
        void toggleNativeFullscreen();
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

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('msfullscreenchange', handleFullscreenChange);
    handleFullscreenChange();

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

onMounted(() => {
    if (isThemeSandbox.value) {
        nextTick(() => refreshThemeDebug());
    }
});

watch(globalIsDark, () => {
    if (isThemeSandbox.value) {
        nextTick(() => refreshThemeDebug());
    }
}, { flush: 'post' });
</script>
<template>
    <TooltipProvider>
        <template v-if="isThemeSandbox">
            <div v-if="isThemeSandboxNoLayout" class="min-h-screen bg-background text-foreground">
                <div class="mx-auto max-w-5xl p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-lg font-semibold">ChapterEditor Theme Sandbox</h1>
                            <p class="text-sm text-muted-foreground">No layout mode. Use `?theme_sandbox=nolayout`.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <ThemeToggle />
                            <Button variant="outline" size="sm" @click="refreshThemeDebug">Refresh</Button>
                            <Button variant="outline" size="sm"
                                @click="router.visit(window.location.pathname)">Exit</Button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <Card class="bg-card text-card-foreground">
                            <CardHeader>
                                <CardTitle class="text-base">Theme State</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2 text-sm">
                                <div class="flex flex-wrap gap-x-6 gap-y-1">
                                    <div><span class="text-muted-foreground">appearance:</span> {{ globalAppearance }}
                                    </div>
                                    <div><span class="text-muted-foreground">isDark:</span> {{ globalIsDark }}</div>
                                    <div><span class="text-muted-foreground">html.dark:</span> {{ themeDebug.htmlHasDark
                                    }}</div>
                                    <div><span class="text-muted-foreground">step:</span> {{ themeSandboxStep }}</div>
                                </div>
                                <Separator />
                                <div class="grid gap-1">
                                    <div><span class="text-muted-foreground">--background:</span> {{
                                        themeDebug.background }}</div>
                                    <div><span class="text-muted-foreground">--foreground:</span> {{
                                        themeDebug.foreground }}</div>
                                    <div><span class="text-muted-foreground">--card:</span> {{ themeDebug.card }}</div>
                                    <div><span class="text-muted-foreground">--sidebar-background:</span> {{
                                        themeDebug.sidebarBackground }}</div>
                                </div>
                                <Separator />
                                <div class="grid gap-1">
                                    <div><span class="text-muted-foreground">body bg:</span> {{ themeDebug.bodyBg }}
                                    </div>
                                    <div><span class="text-muted-foreground">inset bg:</span> {{ themeDebug.insetBg }}
                                    </div>
                                    <div><span class="text-muted-foreground">sample bg-background:</span> {{
                                        themeDebug.sampleBgBackground }}</div>
                                    <div><span class="text-muted-foreground">sample bg-card:</span> {{
                                        themeDebug.sampleBgCard }}</div>
                                    <div><span class="text-muted-foreground">sample bg-muted:</span> {{
                                        themeDebug.sampleBgMuted }}</div>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="grid gap-4 md:grid-cols-3">
                            <Card ref="sampleBgBackgroundEl" class="bg-background">
                                <CardHeader>
                                    <CardTitle class="text-base">bg-background</CardTitle>
                                </CardHeader>
                                <CardContent class="text-sm text-muted-foreground">Should flip with theme.</CardContent>
                            </Card>
                            <Card ref="sampleBgCardEl" class="bg-card">
                                <CardHeader>
                                    <CardTitle class="text-base">bg-card</CardTitle>
                                </CardHeader>
                                <CardContent class="text-sm text-muted-foreground">Card surface color.</CardContent>
                            </Card>
                            <Card ref="sampleBgMutedEl" class="bg-muted">
                                <CardHeader>
                                    <CardTitle class="text-base">bg-muted</CardTitle>
                                </CardHeader>
                                <CardContent class="text-sm text-muted-foreground">Muted surface color.</CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>

            <AppLayout v-else class="h-screen overflow-hidden bg-background text-foreground">
                <div class="mx-auto w-full max-w-5xl p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-lg font-semibold">ChapterEditor Theme Sandbox</h1>
                            <p class="text-sm text-muted-foreground">
                                Use `?theme_sandbox=1` (with layout) or `?theme_sandbox=nolayout`.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <ThemeToggle />
                            <Button variant="outline" size="sm" @click="refreshThemeDebug">Refresh</Button>
                            <Button variant="outline" size="sm"
                                @click="router.visit(window.location.pathname)">Exit</Button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <Card class="bg-card text-card-foreground">
                            <CardHeader>
                                <CardTitle class="text-base">Theme State</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2 text-sm">
                                <div class="flex flex-wrap gap-x-6 gap-y-1">
                                    <div><span class="text-muted-foreground">appearance:</span> {{ globalAppearance }}
                                    </div>
                                    <div><span class="text-muted-foreground">isDark:</span> {{ globalIsDark }}</div>
                                    <div><span class="text-muted-foreground">html.dark:</span> {{ themeDebug.htmlHasDark
                                    }}</div>
                                    <div><span class="text-muted-foreground">step:</span> {{ themeSandboxStep }}</div>
                                </div>
                                <Separator />
                                <div class="grid gap-1">
                                    <div><span class="text-muted-foreground">--background:</span> {{
                                        themeDebug.background }}</div>
                                    <div><span class="text-muted-foreground">--foreground:</span> {{
                                        themeDebug.foreground }}</div>
                                    <div><span class="text-muted-foreground">--card:</span> {{ themeDebug.card }}</div>
                                    <div><span class="text-muted-foreground">--sidebar-background:</span> {{
                                        themeDebug.sidebarBackground }}</div>
                                </div>
                                <Separator />
                                <div class="grid gap-1">
                                    <div><span class="text-muted-foreground">body bg:</span> {{ themeDebug.bodyBg }}
                                    </div>
                                    <div><span class="text-muted-foreground">inset bg:</span> {{ themeDebug.insetBg }}
                                    </div>
                                    <div><span class="text-muted-foreground">sample bg-background:</span> {{
                                        themeDebug.sampleBgBackground }}</div>
                                    <div><span class="text-muted-foreground">sample bg-card:</span> {{
                                        themeDebug.sampleBgCard }}</div>
                                    <div><span class="text-muted-foreground">sample bg-muted:</span> {{
                                        themeDebug.sampleBgMuted }}</div>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="grid gap-4 md:grid-cols-3">
                            <Card ref="sampleBgBackgroundEl" class="bg-background">
                                <CardHeader>
                                    <CardTitle class="text-base">bg-background</CardTitle>
                                </CardHeader>
                                <CardContent class="text-sm text-muted-foreground">Should flip with theme.</CardContent>
                            </Card>
                            <Card ref="sampleBgCardEl" class="bg-card">
                                <CardHeader>
                                    <CardTitle class="text-base">bg-card</CardTitle>
                                </CardHeader>
                                <CardContent class="text-sm text-muted-foreground">Card surface color.</CardContent>
                            </Card>
                            <Card ref="sampleBgMutedEl" class="bg-muted">
                                <CardHeader>
                                    <CardTitle class="text-base">bg-muted</CardTitle>
                                </CardHeader>
                                <CardContent class="text-sm text-muted-foreground">Muted surface color.</CardContent>
                            </Card>
                        </div>

                        <div v-if="themeSandboxStep >= 1" class="rounded-xl border border-border bg-card p-4">
                            <div class="text-sm font-medium">Step 1: Paper Card only</div>
                            <div class="text-xs text-muted-foreground mb-3">Adds `ChapterEditorPaperCard` only.</div>
                            <ChapterEditorPaperCard :context="paperCardContext" />
                        </div>
                    </div>
                </div>
            </AppLayout>
        </template>

        <template v-else>
            <!-- Chat Mode Layout - DISABLED due to dark mode issues
            <ChatModeLayout v-if="showChatMode" :project="memoizedProject" :chapter="memoizedChapter"
                :chapter-title="chapterTitle" :chapter-content="chapterContent" :current-word-count="currentWordCount"
                :target-word-count="targetWordCount" :progress-percentage="progressPercentage"
                :writing-quality-score="writingQualityScore" :is-valid="isValid" :is-saving="isSaving"
                :show-preview="showPreview" :is-generating="isGenerating" :generation-progress="generationProgress"
                :history-index="historyIndex" :content-history-length="contentHistory.length"
                :selected-text="selectedText" @update:chapter-title="chapterTitle = $event"
                @update:chapter-content="chapterContent = $event" @update:selected-text="selectedText = $event"
                @update:show-preview="showPreview = $event" @save="(autoSave) => saveChapter(autoSave)"
                @undo="handleUndo" @redo="handleRedo" @exit-chat-mode="exitChatMode" />
            -->
            <!-- Citation Verification Layout -->
            <CitationVerificationLayout v-if="showCitationMode" :project="memoizedProject" :chapter="memoizedChapter"
                :chapter-title="chapterTitle" :chapter-content="chapterContent" :current-word-count="currentWordCount"
                :target-word-count="targetWordCount" :progress-percentage="progressPercentage"
                @exit-citation-mode="exitCitationMode" />

            <!-- Fullscreen Layout with Sidebars -->
            <!-- Fullscreen Layout with Sidebars -->
            <div v-else-if="isNativeFullscreen"
                class="flex h-screen flex-col overflow-hidden bg-background dark:bg-background font-sans selection:bg-primary/20 transition-colors duration-300">
                <!-- Ambient Background Effects -->
                <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                    <div
                        class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-blue-500/5 blur-[120px]">
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
                                <Button @click="router.visit(route('projects.show', props.project.slug))"
                                    variant="ghost" size="icon"
                                    class="h-10 w-10 rounded-full hover:bg-primary/10 hover:text-primary transition-all duration-300">
                                    <ArrowLeft class="h-5 w-5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Back to Project</p>
                            </TooltipContent>
                        </Tooltip>

                        <div class="flex flex-col">
                            <!-- SafeHtmlText DISABLED - replaced with h1 -->
                            <h1 class="text-lg font-bold tracking-tight text-foreground/90 font-display">{{
                                props.project.title }}</h1>
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
                            <!-- Stats DISABLED in ChapterEditor -->

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button @click="toggleNativeFullscreen"
                                        :variant="isNativeFullscreen ? 'secondary' : 'ghost'" size="icon"
                                        class="h-9 w-9 rounded-full transition-all hover:bg-muted">
                                        <Minimize2 v-if="isNativeFullscreen" class="h-4.5 w-4.5" />
                                        <Maximize2 v-else class="h-4.5 w-4.5" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ isNativeFullscreen ? 'Exit fullscreen' : 'Enter fullscreen' }}</p>
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
                                <div v-else-if="generationPhase === 'Complete'"
                                    class="h-2 w-2 rounded-full bg-green-500">
                                </div>
                                <div v-else class="h-2 w-2 rounded-full bg-red-500"></div>
                            </div>
                            <p class="flex-1 text-xs text-blue-800 dark:text-blue-200">{{ generationProgress }}</p>
                        </div>
                    </div>
                </div>

                <!-- WritingStatistics DISABLED in ChapterEditor -->

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
                                        <p class="text-xs text-muted-foreground">{{ memoizedAllChapters.length }}
                                            Chapters
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
                                <ChapterEditorPaperCard :context="paperCardContext" />

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
                        <ChapterEditorRightSidebar v-if="showRightSidebarInFullscreen"
                            :container-class="'w-72 flex-shrink-0 border-l border-border/40 bg-background/40 backdrop-blur-md'"
                            v-bind="rightSidebarCommonProps" />
                    </Transition>
                </div>
            </div>

            <!-- Normal mode - render inside AppLayout -->
            <AppLayout v-else
                class="h-screen overflow-hidden bg-background dark:bg-background transition-colors duration-300">
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
                                <ChapterNavigation :all-chapters="memoizedAllChapters"
                                    :current-chapter="memoizedChapter" :project="memoizedProject"
                                    :outlines="project.outlines || []" :faculty-chapters="facultyChapters || []"
                                    :current-word-count="currentWordCount" :target-word-count="targetWordCount"
                                    :writing-quality-score="writingQualityScore"
                                    :chapter-content-length="chapterContent.length" @go-to-chapter="goToChapter"
                                    @generate-next-chapter="generateNextChapter" />
                            </div>
                        </ScrollArea>
                    </aside>

                    <!-- Center Workspace -->
                    <main
                        class="flex-1 flex flex-col relative z-10 min-w-0 bg-background/30 dark:bg-background transition-all duration-300">

                        <!-- Mobile Header (Original Layout Preserved) -->
                        <header
                            class="md:hidden flex flex-wrap items-start justify-between border-b border-border/40 bg-background/60 backdrop-blur-md px-4 py-3 relative z-30 transition-all duration-300 gap-y-2">

                            <!-- Left: Navigation Toggles -->
                            <div class="flex items-center gap-2 flex-shrink-0 order-1">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="icon"
                                            class="h-9 w-9 text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/50 rounded-full"
                                            @click="router.visit(route('projects.writing', props.project.slug))">
                                            <ArrowLeft class="w-4 h-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Back to Writing</TooltipContent>
                                </Tooltip>

                                <div class="h-6 w-px bg-border/50 mx-1 hidden lg:block"></div>

                                <Button variant="ghost" size="icon" @click="showLeftSidebar = true"
                                    class="lg:hidden text-muted-foreground hover:text-foreground h-9 w-9 hover:bg-muted/50 rounded-full">
                                    <Menu class="h-4.5 w-4.5" />
                                </Button>
                            </div>

                            <!-- Right: Mobile Right Sidebar Toggle -->
                            <div class="flex items-center order-2">
                                <Button variant="ghost" size="icon" @click="showRightSidebar = true"
                                    class="text-muted-foreground hover:text-foreground h-9 w-9 hover:bg-muted/50 rounded-full">
                                    <Brain class="h-4.5 w-4.5" />
                                </Button>
                            </div>

                            <!-- Center: Title & Toolbar -->
                            <div class="order-3 w-full flex flex-col gap-3">
                                <!-- Title -->
                                <div class="flex items-start gap-3 w-full min-w-0">
                                    <h1 class="text-xl font-bold tracking-tight text-foreground leading-snug cursor-pointer hover:text-primary/80 transition-colors break-words"
                                        @click="router.visit(route('projects.show', props.project.slug))">
                                        {{ props.project.title }}
                                    </h1>
                                    <Badge variant="secondary"
                                        class="shrink-0 h-5 px-2 text-[10px] font-medium rounded-full bg-secondary/50 border border-border/50 mt-1">
                                        Ch {{ props.chapter.chapter_number }}
                                    </Badge>
                                </div>

                                <!-- Mobile Actions -->
                                <div class="flex items-center gap-2 justify-between mt-1">
                                    <div class="flex items-center gap-2">
                                        <ExportMenu :project="memoizedProject" :current-chapter="memoizedChapter"
                                            :all-chapters="memoizedAllChapters" size="icon" variant="outline"
                                            trigger-element="icon"
                                            button-class="h-8 w-8 rounded-full bg-background/50 backdrop-blur-sm"
                                            class="text-zinc-700 dark:text-zinc-300" />

                                        <Button variant="outline" size="icon"
                                            class="h-8 w-8 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground bg-background/50 backdrop-blur-sm rounded-full"
                                            @click="goToBulkAnalysis">
                                            <Search class="w-4 h-4" />
                                        </Button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button @click="save(false)" :disabled="isSaving"
                                            :variant="isValid ? 'default' : 'secondary'" size="sm"
                                            class="h-8 px-3 text-xs shadow-sm gap-2 rounded-full min-w-[32px]">
                                            <Save class="h-3.5 w-3.5" />
                                            <span v-if="isSaving" class="inline">Saving</span>
                                        </Button>

                                        <Button @click="markAsComplete" :disabled="isSaving" size="sm"
                                            class="h-8 px-3 text-xs shadow-sm gap-2 rounded-full">
                                            <CheckCircle class="h-3.5 w-3.5" />
                                            <span class="sm:hidden">Done</span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </header>



                        <!-- Desktop Double-Decker Header (New) -->
                        <div
                            class="hidden md:flex flex-col w-full z-30 bg-background/60 backdrop-blur-md transition-all duration-300 relative">
                            <!-- Top Row: Navigation & Context -->
                            <div
                                class="h-10 border-b border-border/40 flex items-left justify-left px-4 bg-background/40">
                                <!-- Title & Badge -->
                                <div class="flex items-left gap-3">
                                    <h1 class="text-sm font-bold text-foreground cursor-pointer hover:text-primary transition-colors flex items-center"
                                        @click="router.visit(route('projects.show', props.project.slug))">
                                        <div v-html="props.project.title" class="inline-block"></div>
                                    </h1>
                                    <Badge variant="secondary"
                                        class="h-5 px-2 text-[10px] font-medium rounded-full bg-secondary/50 border border-border/50">
                                        Ch {{ props.chapter.chapter_number }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Bottom Row: Toolbar & Actions -->
                            <div
                                class="h-12 border-b border-border/40 flex items-center justify-between px-4 gap-3 bg-muted/10">
                                <!-- Left Actions: Nav -->
                                <div class="flex items-center gap-1">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button variant="ghost" size="icon"
                                                class="h-8 w-8 text-zinc-700 dark:text-zinc-300 hover:text-foreground rounded-full"
                                                @click="router.visit(route('projects.writing', props.project.slug))">
                                                <ArrowLeft class="w-4 h-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>Back to Writing</TooltipContent>
                                    </Tooltip>

                                    <div class="h-4 w-px bg-border/50 mx-1"></div>

                                    <Button variant="ghost" size="icon"
                                        @click="isLeftSidebarCollapsed = !isLeftSidebarCollapsed"
                                        class="text-muted-foreground hover:text-foreground h-8 w-8 rounded-full">
                                        <PanelLeftClose v-if="!isLeftSidebarCollapsed" class="h-4 w-4" />
                                        <PanelLeftOpen v-else class="h-4 w-4" />
                                    </Button>
                                </div>

                                <!-- Center/Right: Tools & Actions -->
                                <div class="flex items-center gap-3">
                                    <!-- Tools Group -->
                                    <div
                                        class="flex items-center bg-background/50 rounded-full border border-border/50 p-1 backdrop-blur-sm shadow-sm">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="togglePresentationMode"
                                                    :variant="showPresentationMode ? 'secondary' : 'ghost'" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground rounded-full">
                                                    <Eye v-if="!showPresentationMode" class="h-3.5 w-3.5" />
                                                    <Edit2 v-else class="h-3.5 w-3.5" />
                                                    <span class="hidden xl:inline">{{ showPresentationMode ? 'Edit' :
                                                        'Preview'
                                                    }}</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Toggle Preview Mode</TooltipContent>
                                        </Tooltip>

                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="toggleChatMode" variant="ghost" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-muted-foreground hover:text-foreground rounded-full">
                                                    <MessageSquare class="h-3.5 w-3.5 text-blue-500" />
                                                    <span class="hidden xl:inline text-foreground">AI Chat</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Open AI Assistant</TooltipContent>
                                        </Tooltip>

                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="toggleNativeFullscreen" variant="ghost" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-muted-foreground hover:text-foreground rounded-full">
                                                    <Minimize2 v-if="isNativeFullscreen" class="h-3.5 w-3.5" />
                                                    <Maximize2 v-else class="h-3.5 w-3.5" />
                                                    <span class="hidden xl:inline">{{ isNativeFullscreen ? `Exit
                                                        Fullscreen` :
                                                        'Fullscreen'
                                                    }}</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ isNativeFullscreen ? 'Exit fullscreen' : 'Enter fullscreen' }}</p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <div class="w-px h-3 bg-border/50 mx-0.5"></div>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="toggleChapterTheme" variant="ghost" size="sm"
                                                    class="h-7 px-3 text-xs gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground rounded-full">
                                                    <Moon v-if="isEditorDark" class="h-3.5 w-3.5 text-foreground" />
                                                    <Sun v-else class="h-3.5 w-3.5" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Toggle Dark Mode</TooltipContent>
                                        </Tooltip>
                                    </div>

                                    <!-- Actions Group -->
                                    <div class="flex items-center gap-2">
                                        <ExportMenu :project="memoizedProject" :current-chapter="memoizedChapter"
                                            :all-chapters="memoizedAllChapters" size="sm" variant="outline"
                                            trigger-element="button"
                                            button-class="h-9 gap-2 px-3 rounded-full bg-background/50 backdrop-blur-sm"
                                            class="text-zinc-700 dark:text-zinc-300" />

                                        <Button variant="outline" size="sm"
                                            class="h-9 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground bg-background/50 backdrop-blur-sm rounded-full"
                                            @click="goToBulkAnalysis" title="Open bulk analysis">
                                            <Search class="w-4 h-4" />
                                            <span class="text-xs font-medium">Analyze</span>
                                        </Button>
                                    </div>

                                    <div class="flex items-center gap-2 pl-2 border-l border-border/50">
                                        <Button @click="save(false)" :disabled="isSaving"
                                            :variant="isValid ? 'default' : 'secondary'" size="sm"
                                            class="h-9 px-4 text-xs shadow-sm gap-2 rounded-full min-w-[32px]">
                                            <Save class="h-3.5 w-3.5" />
                                            <span class="inline">{{ isSaving ? 'Saving' : 'Save' }}</span>
                                        </Button>

                                        <Button @click="markAsComplete" :disabled="isSaving" size="sm"
                                            class="h-9 px-4 text-xs shadow-sm gap-2 rounded-full">
                                            <CheckCircle class="h-3.5 w-3.5" />
                                            <span>Mark Complete</span>
                                        </Button>

                                        <div class="w-px h-4 bg-border/50 mx-1"></div>

                                        <Button variant="ghost" size="icon"
                                            @click="isRightSidebarCollapsed = !isRightSidebarCollapsed"
                                            class="hidden lg:flex text-muted-foreground hover:text-foreground h-9 w-9 hover:bg-muted/50 rounded-full">
                                            <PanelRightClose v-if="!isRightSidebarCollapsed" class="h-4.5 w-4.5" />
                                            <PanelRightOpen v-else class="h-4.5 w-4.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                                        <h4 class="text-xs font-semibold text-foreground">
                                                            Generating
                                                            Content...</h4>
                                                        <span class="text-xs font-mono text-muted-foreground">{{
                                                            Math.round(generationPercentage) }}%</span>
                                                    </div>
                                                    <div
                                                        class="h-1.5 w-full bg-blue-100 dark:bg-blue-900/40 rounded-full overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-300"
                                                            :style="{ width: `${generationPercentage}%` }">
                                                        </div>
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
                                <div class=" mx-auto py-12 px-6 sm:px-10 min-h-full">
                                    <!-- WritingStatistics DISABLED in ChapterEditor -->


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
                                <span class="hidden sm:inline">Last saved: {{ isSaving ? 'Saving...' : `Just
                                    now`
                                }}</span>
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
                    <ChapterEditorRightSidebar :is-collapsed="isRightSidebarCollapsed"
                        :container-class="'hidden lg:flex w-96 flex-col border-l border-border/40 bg-background/60 backdrop-blur-xl z-20 transition-all duration-300'"
                        v-bind="rightSidebarCommonProps" />
                </div>
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

            </AppLayout>
        </template>
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
    background: var(--border);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: color-mix(in oklab, var(--border) 80%, transparent);
}

/* Firefox scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}
</style>
