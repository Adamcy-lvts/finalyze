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
import { ArrowLeft, Brain, CheckCircle, Eye, Maximize2, Menu, MessageSquare, Moon, PenTool, Save, Sun, Target, BookCheck, PanelLeftClose, PanelLeftOpen, PanelRightClose, PanelRightOpen } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import axios from 'axios';
import PurchaseModal from '@/components/PurchaseModal.vue';
import { useWordBalance, recordWordUsage } from '@/composables/useWordBalance';

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
import { useDefenseQuestionWatcher } from '@/composables/useDefenseQuestionWatcher';
import { useChapterAnalysis } from '@/composables/useChapterAnalysis';


interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    target_word_count?: number | null;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
    slug?: string;
}

interface ProjectCategory {
    id: number;
    name: string;
    slug: string;
    default_chapter_count: number;
    chapter_structure: any[];
    target_word_count: number;
}

interface ChapterSection {
    id: number;
    section_number: string;
    section_title: string;
    section_description: string;
    target_word_count: number;
    current_word_count: number;
    is_completed: boolean;
    is_required: boolean;
}

interface ProjectOutline {
    id: number;
    chapter_number: number;
    chapter_title: string;
    target_word_count: number;
    completion_threshold: number;
    description: string;
    sections: ChapterSection[];
}

interface FacultyChapter {
    number: number;
    title: string;
    word_count: number;
    completion_threshold: number;
    description: string;
    is_required: boolean;
    sections: Array<{
        number: string;
        title: string;
        description: string;
        word_count: number;
        is_required: boolean;
        tips?: string[];
    }>;
}

interface Project {
    id: number;
    slug: string;
    title: string;
    topic: string;
    type: string;
    status: string;
    mode: 'auto' | 'manual';
    field_of_study: string;
    university: string;
    course: string;
    project_category_id?: number;
    category?: ProjectCategory;
    outlines?: ProjectOutline[];
}

interface Props {
    project: Project;
    chapter: Chapter;
    allChapters: Chapter[];
    facultyChapters?: FacultyChapter[];
    mode?: 'write' | 'edit';
}


const props = defineProps<Props>();

// Core editor state
const chapterTitle = ref(props.chapter.title || '');
const chapterContent = ref(props.chapter.content || '');
const showPreview = ref(true); // Default to preview/presentation mode


// UI Enhancement states
const isDarkMode = ref(false);
const isNativeFullscreen = ref(false);
const showAISidebar = ref(false);
const showStatistics = ref(false);
const activeTab = ref('write');
const selectedText = ref('');
const cursorPosition = ref(0);
const showChatMode = ref(false);
const showCitationMode = ref(false);
const showDefensePrep = ref(true);

// Chat persistence - load from localStorage
const loadChatModeFromStorage = () => {
    try {
        const stored = localStorage.getItem(`chatMode_${props.project.id}_${props.chapter.chapter_number}`);
        return stored === 'true';
    } catch (error) {
        console.warn('Failed to load chat mode from localStorage:', error);
        return false;
    }
};

// Save chat mode to localStorage
const saveChatModeToStorage = (isActive: boolean) => {
    try {
        localStorage.setItem(`chatMode_${props.project.id}_${props.chapter.chapter_number}`, String(isActive));
    } catch (error) {
        console.warn('Failed to save chat mode to localStorage:', error);
    }
};

// Mobile responsive states
const showLeftSidebar = ref(false);
const showRightSidebar = ref(false);
const isMobile = ref(false);

// Sidebar collapse states (Desktop)
const isLeftSidebarCollapsed = ref(false);
const isRightSidebarCollapsed = ref(false);

// Fullscreen sidebar states
const showLeftSidebarInFullscreen = ref(true);
const showRightSidebarInFullscreen = ref(true);

// AI Enhancement states
const isGenerating = ref(false);
const generationProgress = ref('');
const generationPercentage = ref(0);
const generationPhase = ref('');
const estimatedTotalWords = ref(0);
const streamWordCount = ref(0);
const eventSource = ref<EventSource | null>(null);
const streamBuffer = ref('');
const lastStreamUpdate = ref(0);
const originalContentForAppend = ref('');
const aiSuggestions = ref<string[]>([]);
const isLoadingSuggestions = ref(false);
const showCitationHelper = ref(false);

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

// Paper Collection states with enhanced source tracking
const isCollectingPapers = ref(false);
const paperCollectionProgress = ref('');
const paperCollectionPhase = ref('');
const collectedPapersCount = ref(0);
const paperCollectionInterval = ref<NodeJS.Timeout | null>(null);
const paperCollectionPercentage = ref(0);
const currentSource = ref<string | null>(null);
const sourcesCompleted = ref<string[]>([]);
const papersPreview = ref<any[]>([]);
const paperCollectionData = ref<any>(null);

// Textarea refs for auto-scroll (legacy - keeping for compatibility)
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const textareaFullscreenRef = ref<HTMLTextAreaElement | null>(null);

// ScrollArea refs for auto-scroll
const editorScrollRef = ref();
const editorFullscreenScrollRef = ref();
const previewScrollRef = ref();
const previewFullscreenScrollRef = ref();


// Presentation mode refs (legacy - keeping for compatibility)
const previewContainerRef = ref<HTMLDivElement | null>(null);
const previewContainerFullscreenRef = ref<HTMLDivElement | null>(null);

// Presentation mode state
const showPresentationMode = ref(false);

// Note: formatContentForPresentation function removed - now using Tiptap RichTextViewer

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

const generateNextChapter = async () => {
    const requiredWords = estimates.chapter(targetWordCount.value || 0);
    if (!ensureBalance(requiredWords, 'generate the next chapter with AI')) {
        return;
    }

    await save();

    // Find the actual next chapter number by looking at existing chapters
    const existingChapterNumbers = props.allChapters.map(ch => ch.chapter_number).sort((a, b) => a - b);
    const currentIndex = existingChapterNumbers.indexOf(props.chapter.chapter_number);

    let nextChapterNumber;
    if (currentIndex === -1 || currentIndex === existingChapterNumbers.length - 1) {
        // If current chapter not found or is the last chapter, generate the next sequential number
        const maxChapterNumber = Math.max(...existingChapterNumbers);
        nextChapterNumber = maxChapterNumber + 1;
    } else {
        // If there are gaps, find the next available number
        const nextExistingChapter = existingChapterNumbers[currentIndex + 1];
        if (nextExistingChapter === props.chapter.chapter_number + 1) {
            // No gap, use existing chapter
            nextChapterNumber = nextExistingChapter;
        } else {
            // There's a gap, fill it
            nextChapterNumber = props.chapter.chapter_number + 1;
        }
    }

    console.log('📝 Generating next chapter:', {
        currentChapter: props.chapter.chapter_number,
        existingChapters: existingChapterNumbers,
        nextChapterNumber
    });

    // Navigate to next chapter with AI generation parameters
    try {
        const url = route('chapters.write', {
            project: props.project.slug,
            chapter: nextChapterNumber,
        });

        console.log('🔗 Generated URL for next chapter:', url);

        // Add AI generation parameters to URL
        const generateUrl = new URL(url, window.location.origin);
        generateUrl.searchParams.set('ai_generate', 'true');
        generateUrl.searchParams.set('generation_type', 'progressive');

        console.log('🚀 Navigating to:', generateUrl.toString());

        router.visit(generateUrl.toString());
    } catch (error) {
        console.error('❌ Error generating next chapter:', error);
        toast.error('Navigation Error', {
            description: 'Failed to navigate to next chapter. Please try again.',
        });
    }
};

// New AI Generation Handler  
const handleAIGeneration = (type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand', options?: { section?: string, mode?: string, selectedText?: string, style?: string }) => {
    if (type === 'section' && options?.section) {
        startSectionGeneration(options.section);
    } else if (type === 'rephrase' && options?.selectedText) {
        startRephraseGeneration(options.selectedText, options.style || 'Academic Formal');
    } else if (type === 'expand' && options?.selectedText) {
        startExpandGeneration(options.selectedText);
    } else {
        startStreamingGeneration(type as 'progressive' | 'outline' | 'improve');
    }
};

const startSectionGeneration = async (sectionType: string) => {
    isGenerating.value = true;
    streamBuffer.value = '';
    streamWordCount.value = 0;
    generationPercentage.value = 5;
    generationPhase.value = 'Papers';
    generationProgress.value = 'Checking for verified sources...';

    // Set estimated word count for section (typically 500-800 words per section)
    estimatedTotalWords.value = 600;

    // Store original content for appending (crucial for section generation)
    originalContentForAppend.value = chapterContent.value || props.chapter.content || '';

    // Enable presentation mode
    showPresentationMode.value = true;

    try {
        // Stage 1: Skip paper collection for section generation (papers should already exist)
        // Section generation assumes papers are already collected from previous chapter work
        generationPercentage.value = 50;
        generationPhase.value = 'Papers';
        generationProgress.value = 'Using existing verified sources...';

        // Small delay to show the papers stage briefly
        await new Promise(resolve => setTimeout(resolve, 500));

        // Stage 2: Start section generation (50-100%)
        generationPhase.value = 'Section';
        generationProgress.value = `Generating ${sectionType} section with verified sources...`;
        generationPercentage.value = 51;

        // Use existing stream endpoint with section parameter
        const url = route('chapters.stream', {
            project: props.project.slug,
            chapter: props.chapter.chapter_number,
        });

        eventSource.value = new EventSource(`${url}?generation_type=section&section_type=${sectionType}`);

        eventSource.value.onmessage = (event) => {
            const data = JSON.parse(event.data);

            switch (data.type) {
                case 'start':
                    generationPhase.value = 'Generating Section';
                    generationPercentage.value = 52;
                    generationProgress.value = `Starting ${sectionType} section generation...`;
                    break;

                case 'content':
                    streamBuffer.value += data.content;
                    streamWordCount.value = data.word_count || countWords(streamBuffer.value);

                    // Throttle UI updates to improve performance and visibility (same as chapter streaming)
                    const now = Date.now();
                    if (now - lastStreamUpdate.value > 150) { // Update UI every 150ms
                        // For section generation, always append to original content
                        // Use stored original content to ensure proper appending
                        const newSectionContent = streamBuffer.value;

                        // Append new section content with proper spacing
                        chapterContent.value = originalContentForAppend.value + "\n\n" + newSectionContent;
                        lastStreamUpdate.value = now;

                        // Progress from 52-95%
                        const progress = Math.min((streamWordCount.value / estimatedTotalWords.value) * 43, 43);
                        generationPercentage.value = Math.max(52, 52 + progress);
                        generationProgress.value = `Generating ${sectionType} section... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;

                        calculateWritingStats();
                        scrollToBottom();
                    }
                    break;

                case 'complete':
                    generationPercentage.value = 100;
                    generationPhase.value = 'Complete';
                    generationProgress.value = `✓ Generated ${sectionType} section (${streamWordCount.value} words)`;

                    // Ensure final content is updated for section generation
                    // Always append to original content for section generation
                    const newSectionContent = streamBuffer.value;
                    chapterContent.value = originalContentForAppend.value + "\n\n" + newSectionContent;

                    // Auto-save
                    save(true);

                    toast.success('✅ Section Generated Successfully', {
                        description: `Added ${sectionType} section with ${streamWordCount.value} words and verified citations.`,
                        duration: 5000,
                    });

                    isGenerating.value = false;
                    eventSource.value?.close();
                    break;

                case 'error':
                    throw new Error(data.message || 'Section generation failed');

                case 'heartbeat':
                    if (generationPercentage.value < 95) {
                        generationPercentage.value += 0.5;
                    }
                    break;
            }
        };

        eventSource.value.onerror = () => {
            isGenerating.value = false;
            generationPhase.value = 'Error';
            generationProgress.value = '❌ Section generation failed';
            toast.error('❌ Generation Failed', {
                description: 'Unable to generate section. Please check your connection and try again.',
            });
            eventSource.value?.close();
        };

    } catch (error) {
        console.error('Section generation failed:', error);
        isGenerating.value = false;
        generationPhase.value = 'Error';
        generationProgress.value = '❌ Section generation error';
        toast.error('❌ Generation Failed', {
            description: 'Section generation encountered an error.',
        });
    }
};

// Rephrase Generation Function
const startRephraseGeneration = async (selectedText: string, style: string) => {
    const selectedWordCount = selectedText.split(/\s+/).length;
    const requiredWords = Math.max(selectedWordCount, estimates.rephrase());
    if (!ensureBalance(requiredWords, `rephrase about ${selectedWordCount} words`)) {
        return;
    }

    console.log('🚀 ChapterEditor - Starting rephrase generation:', {
        selectedTextLength: selectedText.length,
        selectedTextPreview: selectedText.substring(0, 100) + (selectedText.length > 100 ? '...' : ''),
        style,
        timestamp: new Date().toISOString()
    });

    isGenerating.value = true;
    streamBuffer.value = '';
    streamWordCount.value = 0;
    generationPercentage.value = 10;
    generationPhase.value = 'Rephrasing';
    generationProgress.value = 'Preparing to rephrase selected text...';

    // Set estimated word count based on selected text length
    estimatedTotalWords.value = Math.max(selectedWordCount, 50);

    // Get the active editor and store the current selection range
    const activeEditor = richTextEditorFullscreen.value || richTextEditor.value;
    const selectionRange = activeEditor?.getSelectionRange();

    console.log('📍 ChapterEditor - Editor and selection info:', {
        hasActiveEditor: !!activeEditor,
        selectionRange,
        selectedWordCount,
        estimatedWords: estimatedTotalWords.value
    });

    // Store rephrase context for precise replacement
    const rephraseContext = {
        originalText: selectedText,
        range: selectionRange,
        wordCount: selectedWordCount,
        style: style,
        startTime: Date.now()
    };

    console.log('💾 ChapterEditor - Stored rephrase context:', rephraseContext);

    // Enable presentation mode
    showPresentationMode.value = true;

    try {
        generationPercentage.value = 20;
        generationProgress.value = `Rephrasing ${selectedWordCount} words in ${style} style...`;

        const url = route('chapters.stream', {
            project: props.project.slug,
            chapter: props.chapter.chapter_number,
        });

        // Use a rephrase endpoint with the selected text and style
        const rephraseUrl = `${url}?generation_type=rephrase&selected_text=${encodeURIComponent(selectedText)}&style=${encodeURIComponent(style)}`;

        console.log('🌐 ChapterEditor - Creating EventSource:', {
            url: rephraseUrl,
            encodedTextLength: encodeURIComponent(selectedText).length
        });

        eventSource.value = new EventSource(rephraseUrl);

        eventSource.value.onmessage = (event) => {
            const data = JSON.parse(event.data);

            console.log('📨 ChapterEditor - EventSource message received:', {
                type: data.type,
                hasContent: !!data.content,
                contentLength: data.content?.length || 0,
                wordCount: data.word_count
            });

            switch (data.type) {
                case 'start':
                    console.log('▶️ ChapterEditor - Rephrase generation started');
                    generationPhase.value = 'Rephrasing Text';
                    generationPercentage.value = 30;
                    generationProgress.value = `Rephrasing text in ${style} style...`;
                    break;

                case 'content':
                    // For rephrasing, we replace the selected text rather than append
                    streamBuffer.value += data.content;
                    streamWordCount.value = data.word_count || countWords(streamBuffer.value);

                    console.log('📝 ChapterEditor - Content chunk received:', {
                        chunkLength: data.content?.length || 0,
                        totalBufferLength: streamBuffer.value.length,
                        totalWords: streamWordCount.value
                    });

                    // Progress from 30-90%
                    const progress = Math.min((streamWordCount.value / estimatedTotalWords.value) * 60, 60);
                    generationPercentage.value = Math.max(30, 30 + progress);
                    generationProgress.value = `Rephrasing... (${streamWordCount.value} words)`;
                    break;

                case 'complete':
                    console.log('🏁 ChapterEditor - Rephrase generation complete');
                    generationPercentage.value = 100;
                    generationPhase.value = 'Complete';
                    generationProgress.value = `✓ Text rephrased successfully (${streamWordCount.value} words)`;

                    console.log('🔄 ChapterEditor - Starting text replacement:', {
                        hasRange: !!rephraseContext.range,
                        hasContent: !!streamBuffer.value.trim(),
                        range: rephraseContext.range,
                        newContentLength: streamBuffer.value.trim().length,
                        newContentPreview: streamBuffer.value.trim().substring(0, 100) + '...'
                    });

                    // Replace the selected text with the rephrased version using precise positioning
                    if (rephraseContext.range && streamBuffer.value.trim()) {
                        console.log('🎯 ChapterEditor - Attempting text replacement with activeEditor:', {
                            editorType: activeEditor === richTextEditorFullscreen.value ? 'fullscreen' : 'normal',
                            hasActiveEditor: !!activeEditor
                        });

                        const success = activeEditor?.replaceSelection(
                            streamBuffer.value.trim(),
                            rephraseContext.range
                        );

                        console.log('📊 ChapterEditor - Text replacement result:', { success });

                        if (success) {
                            console.log('✅ ChapterEditor - Text replacement successful, updating chapter content');
                            // Update the chapter content to reflect the change
                            setTimeout(() => {
                                const newContent = activeEditor?.getHTML() || chapterContent.value;
                                console.log('📄 ChapterEditor - Updated chapter content length:', newContent.length);
                                chapterContent.value = newContent;
                            }, 100);
                        } else {
                            console.warn('❌ ChapterEditor - Failed to replace selected text, falling back to manual update');
                            // Fallback: user will need to manually replace the text
                            toast.warning('Please manually replace the selected text', {
                                description: 'The rephrased text is ready but couldn\'t be automatically replaced.',
                            });
                        }
                    } else {
                        console.error('❌ ChapterEditor - Cannot replace text:', {
                            missingRange: !rephraseContext.range,
                            missingContent: !streamBuffer.value.trim()
                        });
                    }

                // Auto-save
                save(true);

                toast.success('✅ Text Rephrased Successfully', {
                    description: `Rephrased ${selectedWordCount} words in ${style} style.`,
                    duration: 5000,
                });

                recordWordUsage(
                    streamWordCount.value || selectedWordCount,
                    `Rephrase (${style})`,
                    'chapter',
                    props.chapter.id
                ).catch((err) => console.error('Failed to record word usage (rephrase):', err));

                isGenerating.value = false;
                eventSource.value?.close();
                break;

                case 'error':
                    throw new Error(data.message || 'Text rephrasing failed');

                case 'heartbeat':
                    if (generationPercentage.value < 95) {
                        generationPercentage.value += 0.5;
                    }
                    break;
            }
        };

        eventSource.value.onerror = () => {
            isGenerating.value = false;
            generationPhase.value = 'Error';
            generationProgress.value = '❌ Text rephrasing failed';
            toast.error('❌ Rephrasing Failed', {
                description: 'Unable to rephrase text. Please check your connection and try again.',
            });
            eventSource.value?.close();
        };

    } catch (error) {
        console.error('Text rephrasing failed:', error);
        isGenerating.value = false;
        generationPhase.value = 'Error';
        generationProgress.value = '❌ Rephrasing error';
        toast.error('❌ Rephrasing Failed', {
            description: 'Text rephrasing encountered an error.',
        });
    }
};

const startExpandGeneration = async (selectedText: string) => {
    const selectedWordCount = selectedText.split(/\s+/).length;
    const requiredWords = Math.max(selectedWordCount * 2, estimates.expand());
    if (!ensureBalance(requiredWords, `expand about ${selectedWordCount} words`)) {
        return;
    }

    console.log('📈 ChapterEditor - Starting expand generation:', {
        selectedTextLength: selectedText.length,
        selectedTextPreview: selectedText.substring(0, 100) + (selectedText.length > 100 ? '...' : ''),
        timestamp: new Date().toISOString()
    });

    isGenerating.value = true;
    streamBuffer.value = '';
    streamWordCount.value = 0;
    generationPercentage.value = 10;
    generationPhase.value = 'Expanding';
    generationProgress.value = 'Preparing to expand selected text...';

    // Set estimated word count based on selected text length (2x expansion)
    estimatedTotalWords.value = Math.max(selectedWordCount * 2, 100);

    // Get the active editor and store the current selection range
    const activeEditor = richTextEditorFullscreen.value || richTextEditor.value;
    const selectionRange = activeEditor?.getSelectionRange();

    console.log('📍 ChapterEditor - Editor and selection info for expand:', {
        hasActiveEditor: !!activeEditor,
        selectionRange,
        selectedWordCount,
        estimatedWords: estimatedTotalWords.value
    });

    // Store expand context for precise replacement
    const expandContext = {
        originalText: selectedText,
        range: selectionRange,
        wordCount: selectedWordCount,
        startTime: Date.now()
    };

    console.log('💾 ChapterEditor - Stored expand context:', expandContext);

    try {
        const url = route('chapters.stream', {
            project: props.project.slug,
            chapter: props.chapter.chapter_number,
        });

        // Use EventSource with expand parameters
        const expandUrl = `${url}?generation_type=expand&selected_text=${encodeURIComponent(selectedText)}`;

        console.log('🌐 ChapterEditor - Creating EventSource for expand:', {
            url: expandUrl,
            encodedTextLength: encodeURIComponent(selectedText).length
        });

        eventSource.value = new EventSource(expandUrl);

        eventSource.value.onmessage = (event) => {
            const data = JSON.parse(event.data);

            console.log('📨 ChapterEditor - EventSource message received:', {
                type: data.type,
                hasContent: !!data.content,
                contentLength: data.content?.length || 0,
                wordCount: data.word_count
            });

            switch (data.type) {
                case 'start':
                    console.log('▶️ ChapterEditor - Expand generation started');
                    generationPhase.value = 'Expanding Text';
                    generationPercentage.value = 20;
                    generationProgress.value = 'Starting text expansion...';
                    break;

                case 'content':
                    // Append streamed content
                    streamBuffer.value += data.content;
                    streamWordCount.value = data.word_count || streamBuffer.value.split(/\s+/).filter(word => word.length > 0).length;

                    // Update progress based on estimated words
                    const progress = Math.min((streamWordCount.value / estimatedTotalWords.value) * 90, 90);
                    generationPercentage.value = Math.max(progress, 25);
                    generationProgress.value = `Expanding text... ${streamWordCount.value}/${estimatedTotalWords.value} words`;

                    console.log('📝 ChapterEditor - Expand content received:', {
                        chunkLength: data.content?.length || 0,
                        totalWords: streamWordCount.value,
                        progress: generationPercentage.value
                    });
                    break;

                case 'complete':
                    console.log('✅ ChapterEditor - Expand generation complete');

                    generationPhase.value = 'Complete';
                    generationProgress.value = '✅ Text expansion completed';
                    generationPercentage.value = 100;

                    // Replace the selected text with the expanded version using precise positioning
                    if (expandContext.range && streamBuffer.value.trim()) {
                        console.log('🎯 ChapterEditor - Attempting text replacement for expand');

                        const success = activeEditor?.replaceSelection(
                            streamBuffer.value.trim(),
                            expandContext.range
                        );

                        console.log('📊 ChapterEditor - Expand text replacement result:', { success });

                        if (success) {
                            console.log('✅ ChapterEditor - Expand text replacement successful');
                            // Update the chapter content to reflect the change
                            setTimeout(() => {
                                const newContent = activeEditor?.getHTML() || chapterContent.value;
                                console.log('📄 ChapterEditor - Updated chapter content length after expand:', newContent.length);
                                chapterContent.value = newContent;
                            }, 100);
                        } else {
                            console.warn('❌ ChapterEditor - Failed to replace expanded text');
                            toast.warning('Please manually replace the selected text', {
                                description: 'The expanded text is ready but couldn\'t be automatically replaced.',
                            });
                        }
                    } else {
                        console.error('❌ ChapterEditor - Cannot replace expanded text:', {
                            missingRange: !expandContext.range,
                            missingContent: !streamBuffer.value.trim()
                        });
                    }

                    // Auto-save
                    save(true);

                    toast.success('✅ Text Expanded Successfully', {
                        description: `Expanded ${expandContext.wordCount} words into ${streamWordCount.value} words`,
                    });

                    recordWordUsage(
                        streamWordCount.value || expandContext.wordCount * 2,
                        'Expand text',
                        'chapter',
                        props.chapter.id
                    ).catch((err) => console.error('Failed to record word usage (expand):', err));

                    isGenerating.value = false;
                    eventSource.value?.close();
                    break;

                case 'error':
                    console.error('❌ ChapterEditor - Expand generation error:', data.message);
                    isGenerating.value = false;
                    generationPhase.value = 'Error';
                    generationProgress.value = '❌ Text expansion failed';
                    toast.error('❌ Expansion Failed', {
                        description: data.message || 'Text expansion encountered an error.',
                    });
                    eventSource.value?.close();
                    break;
            }
        };

        eventSource.value.onerror = () => {
            console.error('❌ ChapterEditor - Expand stream error occurred');
            isGenerating.value = false;
            generationPhase.value = 'Error';
            generationProgress.value = '❌ Text expansion failed';
            toast.error('❌ Expansion Failed', {
                description: 'Unable to expand text. Please check your connection and try again.',
            });
            eventSource.value?.close();
        };

    } catch (error) {
        console.error('Text expansion failed:', error);
        isGenerating.value = false;
        generationPhase.value = 'Error';
        generationProgress.value = '❌ Expansion error';
        toast.error('❌ Expansion Failed', {
            description: 'Text expansion encountered an error.',
        });
    }
};

// Paper Collection Functions
const startPaperCollection = async (): Promise<boolean> => {
    isCollectingPapers.value = true;
    paperCollectionPhase.value = 'Starting';
    paperCollectionProgress.value = 'Initializing source collection...';
    generationPercentage.value = 5;

    try {
        // Start paper collection with session-based auth and CSRF token
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            throw new Error('CSRF token not found - please refresh the page');
        }

        const response = await fetch(route('api.projects.paper-collection.start', { project: props.project.slug }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include', // Include session cookies for authentication
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error:', response.status, errorText);

            // Handle 409 Conflict (collection already in progress)
            if (response.status === 409) {
                try {
                    const errorData = JSON.parse(errorText);
                    throw new Error(errorData.message || 'Source collection is already in progress');
                } catch (parseError) {
                    throw new Error('Source collection is already in progress for this project');
                }
            }

            throw new Error(`Failed to start source collection: ${response.status}`);
        }

        // Start monitoring progress
        return await monitorPaperCollection();
    } catch (error) {
        console.error('Paper collection failed:', error);
        paperCollectionPhase.value = 'Error';
        paperCollectionProgress.value = '❌ Source collection failed';
        isCollectingPapers.value = false;
        return false;
    }
};

const monitorPaperCollection = async (): Promise<boolean> => {
    return new Promise((resolve) => {
        let attempts = 0;
        const maxAttempts = 120; // 10 minutes max wait (increased from 5)

        const checkStatus = async () => {
            try {
                const response = await fetch(route('api.projects.paper-collection.status', { project: props.project.slug }));
                const data = await response.json();

                if (data.success && data.data) {
                    // Update all progress data from the enhanced API response
                    paperCollectionData.value = data.data;
                    const status = data.data.status;
                    const count = data.data.papers_count || data.data.count || 0;
                    const message = data.data.message;
                    const percentage = data.data.percentage || 0;
                    const current_source = data.data.current_source;
                    const sources_completed = data.data.sources_completed || [];
                    const papers_preview = data.data.papers_preview || [];

                    // Update reactive states
                    collectedPapersCount.value = count;
                    paperCollectionPercentage.value = percentage;
                    currentSource.value = current_source;
                    sourcesCompleted.value = sources_completed;
                    papersPreview.value = papers_preview;

                    if (status === 'completed') {
                        paperCollectionPhase.value = 'Complete';
                        paperCollectionProgress.value = `✓ Collected ${count} verified sources from ${sources_completed.length} databases`;
                        generationPercentage.value = 50; // Paper collection complete = 50%
                        isCollectingPapers.value = false;

                        if (paperCollectionInterval.value) {
                            clearInterval(paperCollectionInterval.value);
                        }
                        resolve(true);
                        return;
                    } else if (status === 'collecting_papers' || status === 'initializing' || status === 'processing' || status === 'storing') {
                        // Enhanced progress display with source information
                        let phaseDisplay = '';
                        if (current_source) {
                            const sourceNames = {
                                'semantic_scholar': 'Semantic Scholar',
                                'openalex': 'OpenAlex',
                                'crossref': 'CrossRef',
                                'pubmed': 'PubMed'
                            };
                            phaseDisplay = sourceNames[current_source as keyof typeof sourceNames] || current_source;
                        }

                        paperCollectionPhase.value = phaseDisplay || 'Collecting Sources';
                        paperCollectionProgress.value = message || 'Collecting sources from academic databases...';
                        // Use actual percentage from backend, map to 5-45% range for UI
                        generationPercentage.value = Math.min(5 + (percentage * 0.4), 45);
                    } else if (status === 'collection_failed') {
                        paperCollectionPhase.value = 'Error';
                        paperCollectionProgress.value = message || '❌ Source collection failed';
                        isCollectingPapers.value = false;

                        if (paperCollectionInterval.value) {
                            clearInterval(paperCollectionInterval.value);
                        }
                        resolve(false);
                        return;
                    }
                }

                attempts++;
                if (attempts >= maxAttempts) {
                    paperCollectionPhase.value = 'Timeout';
                    paperCollectionProgress.value = '⏱️ Source collection timed out';
                    isCollectingPapers.value = false;

                    if (paperCollectionInterval.value) {
                        clearInterval(paperCollectionInterval.value);
                    }
                    resolve(false);
                }
            } catch (error) {
                console.error('Error checking paper collection status:', error);
                attempts++;
            }
        };

        // Check immediately
        checkStatus();

        // Then check every 5 seconds
        paperCollectionInterval.value = setInterval(checkStatus, 5000);
    });
};

// AI Functions
const startStreamingGeneration = async (type: 'progressive' | 'outline' | 'improve') => {
    const requiredWords = estimates.chapter(targetWordCount.value || 0);
    if (!ensureBalance(requiredWords, 'generate this chapter with AI')) {
        return;
    }

    isGenerating.value = true;
    streamBuffer.value = '';
    streamWordCount.value = 0;
    generationPercentage.value = 5; // Start at 5% for paper collection stage
    generationPhase.value = 'Papers';
    generationProgress.value = 'Checking for verified sources...';

    // Set estimated word count based on chapter and project type
    estimatedTotalWords.value = targetWordCount.value;

    // Automatically enable presentation mode during generation
    showPresentationMode.value = true;

    // Stage 1: Ensure papers are collected (0-50%)
    const papersCollected = await startPaperCollection();

    if (!papersCollected) {
        toast.error('Paper Collection Failed', {
            description: 'Unable to collect verified papers. Please try again.',
        });
        isGenerating.value = false;
        showPresentationMode.value = false;
        return;
    }

    // Stage 2: Start AI generation (50-100%)
    generationPhase.value = 'Initializing';
    generationProgress.value = 'Starting AI generation with verified sources...';
    generationPercentage.value = 51;

    // Smooth progress animation during initialization
    const initProgressInterval = setInterval(() => {
        if (generationPercentage.value < 52 && isGenerating.value) {
            generationPercentage.value += 0.2;
        } else {
            clearInterval(initProgressInterval);
        }
    }, 100);

    const url = route('chapters.stream', {
        project: props.project.slug,
        chapter: props.chapter.chapter_number,
    });

    eventSource.value = new EventSource(`${url}?generation_type=${type}`);

    eventSource.value.onmessage = (event) => {
        const data = JSON.parse(event.data);

        switch (data.type) {
            case 'start':
                generationPhase.value = 'Connecting';
                generationPercentage.value = 52; // Smoother transition from 50%
                generationProgress.value = 'Connecting to AI service...';
                break;

            case 'content':
                generationPhase.value = 'Generating';
                streamBuffer.value += data.content;
                streamWordCount.value = data.word_count || streamBuffer.value.split(/\s+/).filter((word) => word.length > 0).length;

                // Throttle UI updates to improve performance and visibility
                const now = Date.now();
                if (now - lastStreamUpdate.value > 150) { // Update UI every 150ms
                    chapterContent.value = streamBuffer.value;
                    lastStreamUpdate.value = now;

                    // Calculate realistic progress based on word count vs target (52-95% range)
                    const wordProgress = Math.min((streamWordCount.value / estimatedTotalWords.value) * 43, 43); // 43% of the 48% allocation
                    generationPercentage.value = Math.max(52, 52 + wordProgress); // 52-95% range

                    generationProgress.value = `Generating chapter content... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;
                    calculateWritingStats();

                    // Auto-scroll to follow the streaming content
                    scrollToBottom();
                }
                break;

            case 'heartbeat':
                // Keep connection alive, small progress increment
                if (generationPercentage.value < 95) {
                    generationPercentage.value += 0.5; // Smaller increments in 50-100% range
                }
                break;

            case 'complete':
                generationPhase.value = 'Complete';
                generationPercentage.value = 100;
                isGenerating.value = false;

                // Ensure final content is updated
                chapterContent.value = streamBuffer.value;

                const finalWords = data.final_word_count || streamWordCount.value;
                generationProgress.value = `✓ Generated ${finalWords} words successfully`;

                // Record actual usage
                recordWordUsage(
                    finalWords,
                    `Chapter generation (${props.chapter.chapter_number})`,
                    'chapter',
                    props.chapter.id,
                ).catch((err) => console.error('Failed to record word usage (chapter generation):', err));

                // Wait for RichTextEditor to process the markdown conversion before auto-saving
                setTimeout(() => {
                    triggerAutoSave();
                }, 500); // Give editor time to convert markdown to HTML

                // Generation complete - no toast needed as save will show its own

                eventSource.value?.close();
                eventSource.value = null;
                break;

            case 'error':
                generationPhase.value = 'Error';
                generationPercentage.value = 50; // Reset to start of AI stage
                isGenerating.value = false;

                // Handle different error types
                if (data.code === 'OFFLINE_MODE') {
                    generationProgress.value = '📡 AI services offline';
                    toast.error('AI Services Offline', {
                        description: 'Please check your internet connection and try again.',
                    });
                } else {
                    generationProgress.value = '❌ Generation failed';
                    toast.error('Generation Error', {
                        description: data.message || 'Please try again.',
                    });
                }

                eventSource.value?.close();
                eventSource.value = null;
                break;
        }
    };

    eventSource.value.onerror = () => {
        generationPhase.value = 'Error';
        generationPercentage.value = 50; // Reset to start of AI stage
        isGenerating.value = false;
        generationProgress.value = '❌ Connection error';
        toast.error('Connection Error', {
            description: 'Please check your internet connection and try again.',
        });
        eventSource.value?.close();
        eventSource.value = null;
    };
};

const getAISuggestions = async () => {
    if (!selectedText.value) return;

    const requiredWords = estimates.suggestion();
    if (!ensureBalance(requiredWords, 'get AI suggestions')) {
        return;
    }

    isLoadingSuggestions.value = true;
    try {
        const response = await fetch(
            route('chapters.suggestions', {
                project: props.project.slug,
                chapter: props.chapter.chapter_number,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    selected_text: selectedText.value,
                    context: chapterContent.value,
                }),
            },
        );

        const data = await response.json();
        aiSuggestions.value = data.suggestions || [];

        if (aiSuggestions.value.length) {
            recordWordUsage(
                estimates.suggestion(),
                'AI suggestions',
                'chapter',
                props.chapter.id
            ).catch((err) => console.error('Failed to record word usage (suggestions):', err));
        }
    } catch (error) {
        toast.error('Error getting suggestions', { description: 'Please try again.' });
    } finally {
        isLoadingSuggestions.value = false;
    }
};

// Citation functions
const checkCitations = () => {
    showCitationHelper.value = true;
    toast.success('Citation Manager opened!', {
        description: 'Review and verify your citations in the panel below.'
    });
};

// Handle citation insertion from Citation Helper
const insertCitation = (citation: string) => {
    try {
        // Get the active editor (check both regular and fullscreen)
        const activeEditor = richTextEditorFullscreen.value?.editor || richTextEditor.value?.editor;

        if (!activeEditor) {
            toast.error('Editor not found');
            return;
        }

        // Insert citation at current cursor position
        const { from } = activeEditor.state.selection;
        activeEditor.chain()
            .focus()
            .insertContentAt(from, ` ${citation} `)
            .run();

        toast.success('Citation inserted successfully');
    } catch (error) {
        console.error('Failed to insert citation:', error);
        toast.error('Failed to insert citation');
    }
};

// Citation-related computed properties and methods
const richTextEditor = ref<{ editor?: any } | null>(null);
const richTextEditorFullscreen = ref<{ editor?: any } | null>(null);


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
const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle('dark', isDarkMode.value);
    localStorage.setItem('darkMode', isDarkMode.value.toString());
};

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

    // Initialize dark mode from localStorage
    const savedDarkMode = localStorage.getItem('darkMode') === 'true';
    isDarkMode.value = savedDarkMode;
    document.documentElement.classList.toggle('dark', savedDarkMode);

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
});

// Auto-scroll functionality for streaming content
const scrollTimeout: NodeJS.Timeout | null = null;
const scrollToBottom = () => {
    // Use immediate scroll during generation for better responsiveness
    nextTick(() => {
        if (isGenerating.value) {
            // Get the appropriate ScrollArea reference based on mode and screen
            const activeScrollArea = showPresentationMode.value
                ? isNativeFullscreen.value
                    ? previewFullscreenScrollRef.value
                    : previewScrollRef.value
                : isNativeFullscreen.value
                    ? editorFullscreenScrollRef.value
                    : editorScrollRef.value;

            if (activeScrollArea) {
                try {
                    // Get the ScrollArea component
                    const scrollAreaComponent = activeScrollArea;
                    if (!scrollAreaComponent) return;

                    // Get the actual DOM element
                    const scrollAreaEl = scrollAreaComponent.$el || scrollAreaComponent;
                    if (!scrollAreaEl) return;

                    // For ScrollArea component, look for the viewport specifically
                    let viewport = scrollAreaEl.querySelector('[data-radix-scroll-area-viewport]');
                    if (!viewport) viewport = scrollAreaEl.querySelector('[data-viewport]');
                    if (!viewport) viewport = scrollAreaEl.querySelector('[role="region"]');
                    if (!viewport) viewport = scrollAreaEl.querySelector('div[style*="overflow"]');
                    if (!viewport) viewport = scrollAreaEl.querySelector('[data-scrollable]');

                    // More thorough fallback search
                    if (!viewport) {
                        const possibleViewports = scrollAreaEl.querySelectorAll('div');
                        for (const div of possibleViewports) {
                            const style = window.getComputedStyle(div);
                            if (
                                style.overflow === 'auto' ||
                                style.overflow === 'scroll' ||
                                style.overflowY === 'auto' ||
                                style.overflowY === 'scroll' ||
                                div.scrollHeight > div.clientHeight
                            ) {
                                viewport = div;
                                break;
                            }
                        }
                    }

                    // If still no viewport, try to find the RichTextEditor content
                    if (!viewport) {
                        const richTextEditor = scrollAreaEl.querySelector('.ProseMirror, [contenteditable="true"]');
                        if (richTextEditor) {
                            viewport = richTextEditor.closest('div[style*="overflow"]') ||
                                richTextEditor.closest('[data-radix-scroll-area-viewport]') ||
                                richTextEditor.parentElement;
                        }
                    }

                    if (viewport) {
                        // Multiple scroll attempts for better reliability
                        const maxScroll = viewport.scrollHeight - viewport.clientHeight;

                        // Force immediate scroll to bottom
                        viewport.scrollTop = maxScroll;

                        // Use requestAnimationFrame for smoother scroll
                        requestAnimationFrame(() => {
                            viewport.scrollTop = viewport.scrollHeight;

                            // Backup scroll method
                            if (viewport.scrollTo) {
                                viewport.scrollTo({
                                    top: viewport.scrollHeight,
                                    behavior: 'instant'
                                });
                            }
                        });

                    } else {
                        console.warn('Could not find scrollable viewport in ScrollArea', {
                            scrollAreaEl,
                            children: scrollAreaEl.children,
                            mode: showPresentationMode.value ? 'preview' : 'editor',
                            fullscreen: isNativeFullscreen.value
                        });
                    }
                } catch (error) {
                    console.warn('Auto-scroll failed:', error);
                }
            } else {
                // Fallback: try to scroll the window if no ScrollArea found
                const proseMirror = document.querySelector('.ProseMirror');
                if (proseMirror) {
                    proseMirror.scrollIntoView({ behavior: 'instant', block: 'end' });
                }
            }
        }
    });
};

// Check if we should automatically start AI generation
const checkForAutoGeneration = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const shouldGenerate = urlParams.get('ai_generate') === 'true';
    const generationType = urlParams.get('generation_type');

    if (shouldGenerate && generationType) {
        // Small delay to ensure the UI is fully mounted
        setTimeout(() => {
            if (generationType === 'progressive') {
                startStreamingGeneration('progressive');

                // Clean URL parameters after starting generation
                const url = new URL(window.location.href);
                url.searchParams.delete('ai_generate');
                url.searchParams.delete('generation_type');
                window.history.replaceState({}, '', url.toString());

                // Show a toast to inform the user
                toast.success('🚀 AI Generation Started', {
                    description: `Generating ${props.chapter.title} with AI assistance...`,
                });
            } else if (generationType === 'single') {
                // Handle single generation type if needed
                startStreamingGeneration('progressive');

                // Clean URL parameters after starting generation
                const url = new URL(window.location.href);
                url.searchParams.delete('ai_generate');
                url.searchParams.delete('generation_type');
                window.history.replaceState({}, '', url.toString());

                // Show a toast to inform the user
                toast.success('🚀 AI Generation Started', {
                    description: `Generating ${props.chapter.title} with AI assistance...`,
                });
            }
        }, 1000); // 1 second delay to ensure everything is loaded
    }
};

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
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
    }
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

// Watch for content changes
watch(chapterContent, handleContentChange);
watch(chapterTitle, triggerAutoSave);

// Watch for chapter prop changes (in case of navigation between chapters)
watch(() => props.chapter, async (newChapter) => {
    chapterTitle.value = newChapter.title || '';
    chapterContent.value = newChapter.content || '';

    // Clear current defense questions to avoid showing stale data
    defenseQuestions.value = [];

    // Restart defense watcher for new chapter
    stopDefenseWatching();
    startDefenseWatching();

    // Auto-load defense questions for the new chapter
    await loadDefenseQuestions();

    // Force check content for new chapter
    await nextTick(() => {
        forceDefenseCheck();
    });
}, { immediate: false });

// Defense Questions State
const defenseQuestions = ref<DefenseQuestion[]>([]);
const isLoadingDefenseQuestions = ref(false);
const isGeneratingDefenseQuestions = ref(false);
const lastDefenseQuestionsLoad = ref<Date | null>(null);

// Defense Question Interface
interface DefenseQuestion {
    id: number;
    question: string;
    suggested_answer: string;
    key_points: string[];
    difficulty: 'easy' | 'medium' | 'hard';
    category: string;
    times_viewed: number;
    user_marked_helpful: boolean | null;
}

// Computed property for current chapter
const currentChapter = computed(() => props.chapter);

// Auto-load defense questions when panel opens
const handleDefensePanelToggle = async (isOpen: boolean) => {
    showDefensePrep.value = isOpen;

    if (isOpen && shouldLoadDefenseQuestions()) {
        await loadDefenseQuestions();
    }
};

// Check if should load questions
const shouldLoadDefenseQuestions = () => {
    if (!defenseQuestions.value.length) return true;
    if (!lastDefenseQuestionsLoad.value) return true;

    const sixHoursAgo = new Date(Date.now() - 6 * 60 * 60 * 1000);
    return lastDefenseQuestionsLoad.value < sixHoursAgo;
};

// Load defense questions - use project.id not project.slug
const loadDefenseQuestions = async (forceRefresh = false) => {
    if (isLoadingDefenseQuestions.value) return;

    isLoadingDefenseQuestions.value = true;
    try {
        // Log the request details for debugging
        console.log('Loading defense questions with params:', {
            project_id: props.project.id,
            chapter_number: currentChapter.value?.chapter_number,
            limit: 5,
            force_refresh: forceRefresh
        });

        const response = await axios.get(`/api/projects/${props.project.id}/defense/questions`, {
            params: {
                chapter_number: currentChapter.value?.chapter_number || null,
                limit: 5,
                force_refresh: forceRefresh ? 1 : 0
            },
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        console.log('Defense questions loaded:', response.data);
        console.log('Questions array:', response.data.questions);
        console.log('Questions count:', response.data.questions ? response.data.questions.length : 0);

        defenseQuestions.value = response.data.questions || [];
        lastDefenseQuestionsLoad.value = new Date();

        // Store in localStorage for persistence (chapter-specific)
        if (response.data.questions && response.data.questions.length > 0) {
            const chapterCacheKey = `defense_questions_${props.project.id}_chapter_${currentChapter.value?.chapter_number}`;
            localStorage.setItem(
                chapterCacheKey,
                JSON.stringify({
                    questions: response.data.questions,
                    chapter_number: currentChapter.value?.chapter_number,
                    loaded_at: new Date().toISOString()
                })
            );
        } else if (!forceRefresh) {
            // NO AUTOMATIC GENERATION - Users must click the "Generate Questions" button manually
            console.log('No existing questions found for this chapter');
            console.log(`Current word count: ${currentWordCount.value}/${DEFENSE_THRESHOLD}`);
            console.log('User must click "Generate Questions" button to create defense questions');
        }
    } catch (error: any) {
        console.error('Failed to load defense questions:', error);

        // Detailed error logging
        if (error.response) {
            console.error('Response status:', error.response.status);
            console.error('Response data:', error.response.data);

            // Handle specific error codes
            if (error.response.status === 422) {
                console.error('Validation errors:', error.response.data.errors);

                // Show specific validation error if available
                const firstError = Object.values(error.response.data.errors || {})[0];
                if (firstError && Array.isArray(firstError)) {
                    toast.error(`Validation Error: ${firstError[0]}`);
                } else {
                    toast.error('Invalid request parameters. Please refresh the page.');
                }
            } else if (error.response.status === 500) {
                toast.error('Server error. Please try again later.');
            } else {
                toast.error('Failed to load defense questions');
            }
        } else if (error.request) {
            console.error('No response received:', error.request);
            toast.error('Network error. Please check your connection.');
        } else {
            console.error('Error setting up request:', error.message);
            toast.error('An unexpected error occurred');
        }

        // Set empty array to prevent UI errors
        defenseQuestions.value = [];
    } finally {
        isLoadingDefenseQuestions.value = false;
    }
};

// Stream generate new questions
const generateNewDefenseQuestions = async () => {
    if (isGeneratingDefenseQuestions.value) return;

    const requiredWords = estimates.defense();
    if (!ensureBalance(requiredWords, 'generate defense questions')) {
        return;
    }

    isGeneratingDefenseQuestions.value = true;

    try {
        // First try direct API call for better reliability
        const response = await axios.post(`/api/projects/${props.project.id}/defense/questions/generate`, {
            chapter_number: currentChapter.value.chapter_number
        }, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        if (response.data && response.data.questions) {
            // Replace questions instead of appending for cleaner UI
            defenseQuestions.value = response.data.questions;
            lastDefenseQuestionsLoad.value = new Date();

            // Cache the questions (chapter-specific)
            const chapterCacheKey = `defense_questions_${props.project.id}_chapter_${currentChapter.value?.chapter_number}`;
            localStorage.setItem(chapterCacheKey, JSON.stringify({
                questions: response.data.questions,
                chapter_number: currentChapter.value?.chapter_number,
                loaded_at: new Date().toISOString()
            }));

            toast.success(`Generated ${response.data.questions.length} new questions`);

            recordWordUsage(
                estimates.defense(),
                'Defense questions',
                'chapter',
                props.chapter.id
            ).catch((err) => console.error('Failed to record word usage (defense):', err));
        }
    } catch (error) {
        console.error('Failed to generate defense questions:', error);
        toast.error('Failed to generate defense questions');
    } finally {
        isGeneratingDefenseQuestions.value = false;
    }
};

// Initialize defense question watcher (after generateNewDefenseQuestions is defined)
// Note: Use centralized word count instead of internal calculations
const {
    isWatching: isWatchingForDefense,
    hasTriggeredGeneration,
    startWatching: startDefenseWatching,
    stopWatching: stopDefenseWatching,
    forceCheck: forceDefenseCheck,
    getStatusMessage: getDefenseStatusMessage,
} = useDefenseQuestionWatcher(
    props.project,
    props.chapter,
    chapterContent,
    generateNewDefenseQuestions
);

// Use centralized word count values for defense questions
const meetsDefenseThreshold = wordCountMeetsDefenseThreshold;
const shouldShowDefenseProgress = computed(() => {
    const wordCount = currentWordCount.value;
    return wordCount > 0 && wordCount < DEFENSE_THRESHOLD && !hasTriggeredGeneration.value;
});
const defenseProgressPercentage = wordCountDefenseProgressPercentage;
const defenseWordsRemaining = wordCountDefenseWordsRemaining;

// Mark question as helpful
const markQuestionHelpful = async (questionId: number, helpful: boolean) => {
    try {
        await axios.patch(`/api/projects/${props.project.id}/defense/questions/${questionId}`, {
            user_marked_helpful: helpful
        }, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        // Update local state
        const question = defenseQuestions.value.find(q => q.id === questionId);
        if (question) {
            question.user_marked_helpful = helpful;
        }

        toast.success(helpful ? 'Question marked as helpful' : 'Removed helpful mark');
    } catch (error) {
        console.error('Failed to mark question:', error);
        toast.error('Failed to update question');
    }
};

// Hide question
const hideQuestion = async (questionId: number) => {
    try {
        await axios.delete(`/api/projects/${props.project.id}/defense/questions/${questionId}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            }
        });

        // Remove from local state
        defenseQuestions.value = defenseQuestions.value.filter(q => q.id !== questionId);

        // Update localStorage
        localStorage.setItem(`defense_questions_${props.project.id}`, JSON.stringify({
            questions: defenseQuestions.value,
            loaded_at: new Date().toISOString()
        }));

        toast.success('Question hidden');
    } catch (error) {
        console.error('Failed to hide question:', error);
        toast.error('Failed to hide question');
    }
};

// Load from localStorage on mount if available
onMounted(async () => {
    const chapterCacheKey = `defense_questions_${props.project.id}_chapter_${currentChapter.value?.chapter_number}`;
    const cached = localStorage.getItem(chapterCacheKey);
    let shouldLoadFresh = true;

    if (cached) {
        const parsed = JSON.parse(cached);
        const loadedAt = new Date(parsed.loaded_at);
        const sixHoursAgo = new Date(Date.now() - 6 * 60 * 60 * 1000);

        if (loadedAt > sixHoursAgo && parsed.chapter_number === currentChapter.value?.chapter_number) {
            defenseQuestions.value = parsed.questions;
            lastDefenseQuestionsLoad.value = loadedAt;
            shouldLoadFresh = false;
        }
    }

    // Load defense questions for current chapter if no valid cache exists
    if (shouldLoadFresh) {
        await loadDefenseQuestions();
    }

    // Start watching for content changes to trigger defense question generation
    startDefenseWatching();

    // Force check initial content for defense question eligibility
    await nextTick(() => {
        forceDefenseCheck();
    });

});

// Initialize chapter analysis on mount
onMounted(async () => {
    // Load latest analysis if available
    await getLatestAnalysis();

    // Auto-analyze if chapter has substantial content
    const wordCount = currentWordCount.value;
    if (wordCount >= 800) {
        await analyzeChapter();
    }
});

// Watch for significant content changes to trigger auto-analysis
let analysisTimeout: ReturnType<typeof setTimeout> | null = null;
watch(currentWordCount, (newCount, oldCount) => {
    // Clear existing timeout
    if (analysisTimeout) {
        clearTimeout(analysisTimeout);
    }

    // Only auto-analyze if:
    // 1. Chapter has substantial content (800+ words)
    // 2. Word count increased significantly (100+ words)
    // 3. User stopped typing for 5 seconds (debounce)
    if (newCount >= 800 && Math.abs(newCount - oldCount) >= 100) {
        analysisTimeout = setTimeout(async () => {
            await analyzeChapter();
        }, 5000);
    }
});

</script>

<template>
    <TooltipProvider>
        <!-- Chat Mode Layout -->
        <ChatModeLayout v-if="showChatMode" :project="memoizedProject" :chapter="memoizedChapter"
            :chapter-title="chapterTitle" :chapter-content="chapterContent" :current-word-count="currentWordCount"
            :target-word-count="targetWordCount" :progress-percentage="progressPercentage"
            :writing-quality-score="writingQualityScore" :is-valid="isValid" :is-saving="isSaving"
            :show-preview="showPreview" :is-generating="isGenerating" :generation-progress="generationProgress"
            :history-index="historyIndex" :content-history-length="contentHistory.length" :selected-text="selectedText"
            @update:chapter-title="chapterTitle = $event" @update:chapter-content="chapterContent = $event"
            @update:selected-text="selectedText = $event" @update:show-preview="showPreview = $event"
            @save="(autoSave) => saveChapter(autoSave)" @undo="handleUndo" @redo="handleRedo"
            @exit-chat-mode="exitChatMode" />

        <!-- Citation Verification Layout -->
        <CitationVerificationLayout v-else-if="showCitationMode" :project="memoizedProject" :chapter="memoizedChapter"
            :chapter-title="chapterTitle" :chapter-content="chapterContent" :current-word-count="currentWordCount"
            :target-word-count="targetWordCount" :progress-percentage="progressPercentage"
            @exit-citation-mode="exitCitationMode" />

        <!-- Fullscreen Layout with Sidebars -->
        <!-- Fullscreen Layout with Sidebars -->
        <div v-else-if="isNativeFullscreen"
            class="flex h-screen flex-col overflow-hidden bg-zinc-50 dark:bg-zinc-950 font-sans selection:bg-primary/20">
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
                        <SafeHtmlText
                            as="h1"
                            class="text-lg font-bold tracking-tight text-foreground/90 font-display"
                            :content="props.project.title"
                        />
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
                                <Button @click="toggleDarkMode" variant="ghost" size="icon"
                                    class="h-9 w-9 rounded-full transition-all hover:bg-muted">
                                    <Moon v-if="!isDarkMode" class="h-4.5 w-4.5 text-muted-foreground" />
                                    <Sun v-else class="h-4.5 w-4.5 text-muted-foreground" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Toggle Theme</p>
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
                                                class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Chapter
                                                Title</Label>
                                            <Input id="chapter-title-fs" v-model="chapterTitle"
                                                placeholder="Enter chapter title..."
                                                class="h-auto p-0 border-0 bg-transparent text-2xl font-bold placeholder:text-muted-foreground/40 focus-visible:ring-0 px-0" />
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
                                                class="h-7 text-xs rounded-full">
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
                                            variant="ghost" class="h-8 rounded-full">
                                            <Save class="mr-2 h-3.5 w-3.5" />
                                            Save Draft
                                        </Button>

                                        <Button @click="analyzeChapter"
                                            :disabled="isAnalyzing || currentWordCount < 100" variant="outline"
                                            size="sm" class="h-8 rounded-full">
                                            <BookCheck
                                                :class="['mr-2 h-3.5 w-3.5', { 'animate-pulse': isAnalyzing }]" />
                                            Analyze
                                        </Button>

                                        <Button @click="save(false)"
                                            :disabled="!isValid || currentWordCount < targetWordCount * 0.8" size="sm"
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
                                        }" @update:show-defense-prep="handleDefensePanelToggle"
                                        @generate-more="generateNewDefenseQuestions"
                                        @refresh="() => loadDefenseQuestions(true)" @mark-helpful="markQuestionHelpful"
                                        @hide-question="hideQuestion" />
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
        <AppLayout v-else class="h-screen overflow-hidden">
            <div class="min-h-screen bg-gradient-to-br from-background via-background to-muted/20">
                <div class="w-full px-4 py-6 transition-all duration-300">
                    <!-- Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button @click="router.visit(route('projects.show', props.project.slug))"
                                        variant="ghost" size="icon" class="hidden sm:flex">
                                        <ArrowLeft class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Back to Project</p>
                                </TooltipContent>
                            </Tooltip>

                            <div>
                                <SafeHtmlText
                                    as="h1"
                                    class="text-xl font-bold sm:text-2xl"
                                    :content="props.project.title"
                                />
                                <p class="text-sm text-muted-foreground">
                                    Chapter {{ props.chapter.chapter_number }} • {{ currentWordCount }} / {{
                                        targetWordCount }}
                                    words
                                </p>
                            </div>
                        </div>

                        <!-- Mobile menu buttons -->
                        <div class="flex items-center gap-2">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button @click="showLeftSidebar = true" variant="outline" size="icon"
                                        class="lg:hidden">
                                        <Menu class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Chapter Navigation</p>
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button @click="showRightSidebar = true" variant="outline" size="icon"
                                        class="lg:hidden">
                                        <Brain class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>AI Tools & Defense Prep</p>
                                </TooltipContent>
                            </Tooltip>



                            <!-- Desktop controls -->
                            <div class="hidden items-center gap-2 lg:flex">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="toggleChatMode" :variant="showChatMode ? 'default' : 'outline'"
                                            size="sm">
                                            <MessageSquare class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ showChatMode ? 'Exit' : 'Open' }} AI Chat Assistant</p>
                                    </TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="toggleCitationMode"
                                            :variant="showCitationMode ? 'default' : 'outline'" size="sm">
                                            <BookCheck class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ showCitationMode ? 'Exit' : 'Open' }} Citation Verification</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="showStatistics = !showStatistics"
                                            :variant="showStatistics ? 'default' : 'outline'" size="sm">
                                            <Target class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ showStatistics ? 'Hide' : 'Show' }} Writing Statistics</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="toggleDarkMode" variant="outline" size="sm">
                                            <Moon v-if="!isDarkMode" class="h-4 w-4" />
                                            <Sun v-else class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Switch to {{ isDarkMode ? 'Light' : 'Dark' }} Mode</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="toggleNativeFullscreen"
                                            :variant="isNativeFullscreen ? 'default' : 'outline'" size="sm">
                                            <Maximize2 class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ isNativeFullscreen ? 'Exit' : 'Enter' }} Fullscreen Mode (F11)</p>
                                    </TooltipContent>
                                </Tooltip>

                                <ExportMenu :project="memoizedProject" :current-chapter="memoizedChapter"
                                    :all-chapters="memoizedAllChapters" size="sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="mb-6">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium">Writing Progress</span>
                            <span class="text-sm text-muted-foreground">{{ currentWordCount }} / {{ targetWordCount }}
                                words ({{
                                    Math.round(progressPercentage) }}%)</span>
                        </div>
                        <Progress :model-value="Number(progressPercentage)" class="h-2" />
                    </div>

                    <!-- AI Generation Progress Card (Fullscreen) -->
                    <div v-if="isGenerating" class="mb-6">
                        <div
                            class="relative overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-purple-50 p-4 shadow-sm dark:border-blue-800 dark:from-blue-950/30 dark:to-purple-950/30">
                            <!-- Background Animation -->
                            <div
                                class="absolute inset-0 -skew-x-12 animate-pulse bg-gradient-to-r from-transparent via-white/10 to-transparent">
                            </div>

                            <!-- Header -->
                            <div class="relative z-10 mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600">
                                            <Brain class="h-4 w-4 text-white" />
                                        </div>
                                        <!-- Pulsing ring -->
                                        <div
                                            class="absolute inset-0 h-8 w-8 animate-ping rounded-full bg-gradient-to-br from-blue-500 to-purple-600 opacity-20">
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100">AI Chapter
                                            Generator
                                        </h4>
                                        <p class="text-xs text-blue-700 dark:text-blue-300">{{ generationPhase }}</p>
                                    </div>
                                </div>
                                <Badge variant="outline"
                                    class="border-blue-300 text-xs text-blue-700 dark:border-blue-700 dark:text-blue-300">
                                    {{ Math.round(generationPercentage) }}%
                                </Badge>
                            </div>

                            <!-- Progress Bar -->
                            <div class="relative z-10 mb-3">
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
                                        class="h-3 w-3 animate-bounce rounded-full bg-blue-500"></div>
                                    <div v-else-if="generationPhase === 'Connecting'" class="flex gap-1">
                                        <div class="h-3 w-1 animate-pulse rounded-full bg-blue-500"></div>
                                        <div class="h-3 w-1 animate-pulse rounded-full bg-purple-500"
                                            style="animation-delay: 0.2s"></div>
                                        <div class="h-3 w-1 animate-pulse rounded-full bg-blue-500"
                                            style="animation-delay: 0.4s"></div>
                                    </div>
                                    <div v-else-if="generationPhase === 'Generating'"
                                        class="h-3 w-3 animate-spin rounded-full border-2 border-blue-500 border-t-transparent">
                                    </div>
                                    <div v-else-if="generationPhase === 'Complete'"
                                        class="h-3 w-3 rounded-full bg-green-500">
                                    </div>
                                    <div v-else class="h-3 w-3 rounded-full bg-red-500"></div>
                                </div>
                                <p class="flex-1 text-xs text-blue-800 dark:text-blue-200">{{ generationProgress }}</p>
                            </div>

                            <!-- Quality indicator -->
                            <div v-if="generationPhase === 'Generating' && streamWordCount > 50"
                                class="relative z-10 mt-3 border-t border-blue-200 pt-3 dark:border-blue-800">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-blue-700 dark:text-blue-300">Writing Quality</span>
                                    <div class="flex items-center gap-1">
                                        <div class="flex gap-0.5">
                                            <div v-for="i in 5" :key="i" class="h-2 w-2 rounded-full"
                                                :class="i <= Math.ceil(writingQualityScore / 20) ? 'bg-yellow-400' : 'bg-blue-200 dark:bg-blue-800'">
                                            </div>
                                        </div>
                                        <span class="ml-1 text-blue-600 dark:text-blue-400">{{ writingQualityScore
                                        }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Writing Statistics -->
                    <WritingStatistics :show-statistics="showStatistics" :current-word-count="currentWordCount"
                        :writing-stats="writingStats" :quality-analysis="latestAnalysis" :is-analyzing="isAnalyzing" />

                    <!-- Main Content Grid -->
                    <!-- Main Content Grid -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 transition-all duration-300">
                        <!-- Left Sidebar (Desktop) -->
                        <div v-show="!isLeftSidebarCollapsed"
                            class="hidden lg:block lg:col-span-2 transition-all duration-300">
                            <div class="h-[calc(100vh-320px)] overflow-y-auto space-y-6 custom-scrollbar pr-1">
                                <ChapterNavigation :all-chapters="memoizedAllChapters"
                                    :current-chapter="memoizedChapter" :project="memoizedProject"
                                    :outlines="project.outlines || []" :faculty-chapters="facultyChapters || []"
                                    :current-word-count="currentWordCount" :target-word-count="targetWordCount"
                                    :writing-quality-score="writingQualityScore"
                                    :chapter-content-length="chapterContent.length" @go-to-chapter="goToChapter"
                                    @generate-next-chapter="generateNextChapter" />
                            </div>
                        </div>

                        <!-- Main Editor -->
                        <div :class="[
                            'transition-all duration-300',
                            isLeftSidebarCollapsed && isRightSidebarCollapsed ? 'lg:col-span-12' :
                                isLeftSidebarCollapsed ? 'lg:col-span-9' :
                                    isRightSidebarCollapsed ? 'lg:col-span-10' :
                                        'lg:col-span-7'
                        ]">
                            <Card class="border-[0.5px] border-border/50 transition-all duration-300">
                                <CardHeader class="pb-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="icon"
                                                class="h-6 w-6 hidden lg:flex mr-1 text-muted-foreground hover:text-foreground"
                                                @click="isLeftSidebarCollapsed = !isLeftSidebarCollapsed"
                                                :title="isLeftSidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'">
                                                <PanelLeftClose v-if="!isLeftSidebarCollapsed" class="h-4 w-4" />
                                                <PanelLeftOpen v-else class="h-4 w-4" />
                                            </Button>
                                            <CardTitle class="text-lg">Chapter {{ chapter.chapter_number }}</CardTitle>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="chapter.status === 'approved' ? 'default' : 'secondary'">
                                                {{ chapter.status.replace('_', ' ') }}
                                            </Badge>
                                            <Badge variant="outline" class="text-xs" :class="{
                                                'text-green-600 border-green-200 bg-green-50': (latestAnalysis?.total_score || 0) >= 80,
                                                'text-yellow-600 border-yellow-200 bg-yellow-50': (latestAnalysis?.total_score || 0) >= 70 && (latestAnalysis?.total_score || 0) < 80,
                                                'text-orange-600 border-orange-200 bg-orange-50': (latestAnalysis?.total_score || 0) >= 60 && (latestAnalysis?.total_score || 0) < 70,
                                                'text-red-600 border-red-200 bg-red-50': (latestAnalysis?.total_score || 0) < 60
                                            }">
                                                {{ latestAnalysis?.total_score ? Math.round(latestAnalysis.total_score)
                                                    :
                                                    writingQualityScore }}% Quality
                                            </Badge>
                                            <Button variant="ghost" size="icon"
                                                class="h-6 w-6 hidden lg:flex ml-1 text-muted-foreground hover:text-foreground"
                                                @click="isRightSidebarCollapsed = !isRightSidebarCollapsed"
                                                :title="isRightSidebarCollapsed ? 'Expand Tools' : 'Collapse Tools'">
                                                <PanelRightClose v-if="!isRightSidebarCollapsed" class="h-4 w-4" />
                                                <PanelRightOpen v-else class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent class="space-y-4">
                                    <!-- Chapter Title Input -->
                                    <div class="space-y-2">
                                        <Label for="chapter-title">Chapter Title</Label>
                                        <Input id="chapter-title" v-model="chapterTitle"
                                            placeholder="Enter chapter title..." class="text-lg font-medium" />
                                    </div>

                                    <!-- Content Editor -->
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <Label for="chapter-content">Content</Label>
                                            <div class="flex items-center gap-2">
                                                <Button @click="togglePresentationMode"
                                                    :variant="showPresentationMode ? 'default' : 'outline'" size="sm">
                                                    <Eye class="mr-1 h-4 w-4" />
                                                    {{ showPresentationMode ? 'Edit Mode' : 'Preview Mode' }}
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Rich Text Editor -->
                                        <ScrollArea ref="editorFullscreenScrollRef" class="h-[calc(100vh-320px)] w-full"
                                            v-show="!showPresentationMode">
                                            <RichTextEditor v-model="chapterContent"
                                                placeholder="Start writing your chapter..." min-height="600px"
                                                class="text-base leading-relaxed" ref="richTextEditorFullscreen"
                                                @update:selected-text="(text) => { selectedText = text; console.log('📋 ChapterEditor Fullscreen - Selected text updated:', { length: text.length, preview: text.substring(0, 50) + (text.length > 50 ? '...' : '') }); }" />
                                        </ScrollArea>


                                        <!-- Presentation View -->
                                        <ScrollArea ref="previewFullscreenScrollRef"
                                            class="h-[calc(100vh-320px)] w-full" v-show="showPresentationMode">
                                            <RichTextViewer :content="chapterContent" :show-font-controls="false"
                                                class="min-h-[600px] rounded-md border border-border/50 bg-background p-6"
                                                style="font-family: 'Times New Roman', serif; line-height: 1.8" />
                                        </ScrollArea>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-col gap-2 pt-4 sm:flex-row">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="save(false)" :disabled="!isValid || isSaving" size="sm"
                                                    class="flex-1 sm:flex-none">
                                                    <Save class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <span class="hidden sm:inline">{{ isSaving ? 'Saving...' : 'Save Draft' }}</span>
                                                    <span class="sm:hidden">{{ isSaving ? 'Saving...' : 'Save' }}</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Save chapter as draft (Ctrl+S)</p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="analyzeChapter"
                                                    :disabled="isAnalyzing || currentWordCount < 100" variant="outline"
                                                    size="sm" class="flex-1 sm:flex-none">
                                                    <BookCheck
                                                        :class="['mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4', { 'animate-pulse': isAnalyzing }]" />
                                                    <span class="hidden sm:inline">{{ isAnalyzing ? 'Analyzing...' : 'Analyze Quality' }}</span>
                                                    <span class="sm:hidden">{{ isAnalyzing ? 'Analyzing...' : 'Analyze'
                                                        }}</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Run academic quality analysis ({{ currentWordCount }}/100 words min)
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="showPreview = !showPreview" variant="outline" size="sm"
                                                    class="flex-1 sm:flex-none">
                                                    <Eye v-if="!showPreview"
                                                        class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <PenTool v-else class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <span class="hidden sm:inline">{{ showPreview ? 'Edit' : 'Preview'
                                                        }}</span>
                                                    <span class="sm:hidden">{{ showPreview ? 'Edit' : 'Preview'
                                                        }}</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ showPreview ? 'Switch to edit mode' : 'Switch to preview mode' }}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="save(false)"
                                                    :disabled="!isValid || currentWordCount < targetWordCount * 0.8"
                                                    size="sm" class="flex-1 sm:flex-none">
                                                    <CheckCircle class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <span class="hidden sm:inline">Save & Mark Complete</span>
                                                    <span class="sm:hidden">Complete</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Mark chapter as complete (requires 80% of target word count)</p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <ExportMenu :project="memoizedProject" :current-chapter="memoizedChapter"
                                            :all-chapters="memoizedAllChapters" size="sm" variant="outline"
                                            class="flex-1 sm:flex-none" />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Right Sidebar (Desktop) -->
                        <div v-show="!isRightSidebarCollapsed"
                            class="hidden lg:block lg:col-span-3 transition-all duration-300">
                            <div class="h-[calc(100vh-40px)] overflow-y-auto space-y-4 custom-scrollbar px-1">
                                <AISidebar :project="memoizedProject" :chapter="memoizedChapter"
                                    :is-generating="isGenerating" :selected-text="selectedText"
                                    :is-loading-suggestions="isLoadingSuggestions"
                                    :show-citation-helper="showCitationHelper" :chapter-content="chapterContent"
                                    :current-word-count="currentWordCount" :target-word-count="targetWordCount"
                                    @start-streaming-generation="handleAIGeneration"
                                    @get-ai-suggestions="getAISuggestions"
                                    @update:show-citation-helper="showCitationHelper = $event"
                                    @insert-citation="insertCitation" @check-citations="checkCitations" />

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
                                    }" @update:show-defense-prep="handleDefensePanelToggle"
                                    @generate-more="generateNewDefenseQuestions"
                                    @refresh="() => loadDefenseQuestions(true)" @mark-helpful="markQuestionHelpful"
                                    @hide-question="hideQuestion" />

                            </div>
                        </div>
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
                        @get-ai-suggestions="getAISuggestions"
                        @update:show-citation-helper="showCitationHelper = $event" @check-citations="checkCitations" />

                    <!-- Credit balance modal -->
                    <PurchaseModal
                        :open="showPurchaseModal"
                        :current-balance="balance"
                        :required-words="requiredWordsForModal"
                        :action="actionDescriptionForModal"
                        @update:open="(v) => showPurchaseModal = v"
                        @close="closePurchaseModal"
                    />
                </div>
            </div>
        </AppLayout>
    </TooltipProvider>
</template>
