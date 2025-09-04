<!-- resources/js/Pages/Projects/ChapterEditor.vue -->
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
import { router } from '@inertiajs/vue3';
import { ArrowLeft, Brain, CheckCircle, Eye, Maximize2, Menu, MessageSquare, Moon, PenTool, Save, Sun, Target } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

// Import extracted components with lazy loading for performance
import WritingStatistics from '@/components/chapter-editor/WritingStatistics.vue';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import { defineAsyncComponent } from 'vue';

// Lazy load heavy components to improve navigation performance
const AISidebar = defineAsyncComponent(() => import('@/components/chapter-editor/AISidebar.vue'));
const DefensePreparationPanel = defineAsyncComponent(() => import('@/components/chapter-editor/DefensePreparationPanel.vue'));
const ChapterNavigation = defineAsyncComponent(() => import('@/components/chapter-editor/ChapterNavigation.vue'));
const MobileNavOverlay = defineAsyncComponent(() => import('@/components/chapter-editor/MobileNavOverlay.vue'));
const ChatModeLayout = defineAsyncComponent(() => import('@/components/chapter-editor/ChatModeLayout.vue'));

// Import composables
import { useAutoSave } from '@/composables/useAutoSave';
import { useTextHistory } from '@/composables/useTextHistory';
import { useWritingStats } from '@/composables/useWritingStats';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
}

interface ProjectCategory {
    id: number;
    name: string;
    slug: string;
    default_chapter_count: number;
    chapter_structure: any[];
    target_word_count: number;
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
}

interface Props {
    project: Project;
    chapter: Chapter;
    allChapters: Chapter[];
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
const showDefensePrep = ref(true);
const showChatMode = ref(false);

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
const aiSuggestions = ref<string[]>([]);
const isLoadingSuggestions = ref(false);
const showCitationHelper = ref(false);

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

const { contentHistory, historyIndex, addToHistory, undo, redo, canUndo, canRedo } = useTextHistory(props.chapter.content || '');

const { writingStats, currentWordCount, writingQualityScore, calculateWritingStats } = useWritingStats(chapterContent);

// Computed properties with better memoization
const targetWordCount = computed(() => {
    const baseCount = props.project.type === 'undergraduate' ? 2500 : 3500;
    return props.chapter.chapter_number === 1 || props.chapter.chapter_number === 5 ? Math.round(baseCount * 0.8) : baseCount;
});

const progressPercentage = computed(() => {
    const target = targetWordCount.value;
    const current = currentWordCount.value;
    return target > 0 ? Math.min((current / target) * 100, 100) : 0;
});

const isValid = computed(() => {
    const title = chapterTitle.value?.trim();
    const content = chapterContent.value?.trim();
    return title && title.length > 0 && content && content.length > 50;
});

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
            if (!autoSave) {
                toast.success('✅ Chapter Saved!', {
                    description: `${data.word_count} words | Quality Score: ${writingQualityScore.value}%`,
                });
            }
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
    router.visit(
        route('chapters.edit', {
            project: props.project.slug,
            chapter: chapterNumber,
        }),
    );
};

const generateNextChapter = async () => {
    const nextChapterNumber = props.chapter.chapter_number + 1;
    await save();
    router.visit(
        route('chapters.edit', {
            project: props.project.slug,
            chapter: nextChapterNumber,
        }),
        {
            data: { generate: true },
        },
    );
};

// AI Functions
const startStreamingGeneration = async (type: 'progressive' | 'outline' | 'improve') => {
    isGenerating.value = true;
    streamBuffer.value = '';
    streamWordCount.value = 0;
    generationPercentage.value = 0;
    generationPhase.value = 'Initializing';
    generationProgress.value = 'Initializing AI generation...';

    // Set estimated word count based on chapter and project type
    estimatedTotalWords.value = targetWordCount.value;

    // Automatically enable presentation mode during generation
    showPresentationMode.value = true;

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
                generationPercentage.value = 10;
                generationProgress.value = 'Connecting to AI service...';
                break;

            case 'content':
                generationPhase.value = 'Generating';
                streamBuffer.value += data.content;
                chapterContent.value = streamBuffer.value;
                streamWordCount.value = data.word_count || streamBuffer.value.split(/\s+/).filter((word) => word.length > 0).length;

                // Calculate realistic progress based on word count vs target
                const wordProgress = Math.min((streamWordCount.value / estimatedTotalWords.value) * 90, 90);
                generationPercentage.value = Math.max(15, wordProgress);

                generationProgress.value = `Generating chapter content... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;
                calculateWritingStats();

                // Auto-scroll to follow the streaming content
                scrollToBottom();
                break;

            case 'heartbeat':
                // Keep connection alive, small progress increment
                if (generationPercentage.value < 85) {
                    generationPercentage.value += 1;
                }
                break;

            case 'complete':
                generationPhase.value = 'Complete';
                generationPercentage.value = 100;
                isGenerating.value = false;
                generationProgress.value = `✓ Generated ${data.final_word_count || streamWordCount.value} words successfully`;
                triggerAutoSave();

                toast.success('✨ Generation Complete!', {
                    description: `${data.final_word_count} words | Quality Score: ${writingQualityScore.value}%`,
                });

                eventSource.value?.close();
                eventSource.value = null;
                break;

            case 'error':
                generationPhase.value = 'Error';
                generationPercentage.value = 0;
                isGenerating.value = false;
                generationProgress.value = '❌ Generation failed';
                toast.error('Generation Error', {
                    description: data.message || 'Please try again.',
                });
                eventSource.value?.close();
                eventSource.value = null;
                break;
        }
    };

    eventSource.value.onerror = () => {
        generationPhase.value = 'Error';
        generationPercentage.value = 0;
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
    } catch (error) {
        toast.error('Error getting suggestions', { description: 'Please try again.' });
    } finally {
        isLoadingSuggestions.value = false;
    }
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
const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle('dark', isDarkMode.value);
    localStorage.setItem('darkMode', isDarkMode.value.toString());
};

// Chat mode toggle with persistence
const toggleChatMode = () => {
    showChatMode.value = !showChatMode.value;
    saveChatModeToStorage(showChatMode.value);
};

const exitChatMode = () => {
    showChatMode.value = false;
    saveChatModeToStorage(false);
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
let scrollTimeout: NodeJS.Timeout | null = null;
const scrollToBottom = () => {
    // Throttle scroll calls to avoid performance issues
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
    }

    scrollTimeout = setTimeout(() => {
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

                        // Fallback to the first child div that might be scrollable
                        if (!viewport) {
                            const possibleViewports = scrollAreaEl.querySelectorAll('div');
                            for (const div of possibleViewports) {
                                const style = window.getComputedStyle(div);
                                if (
                                    style.overflow === 'auto' ||
                                    style.overflow === 'scroll' ||
                                    style.overflowY === 'auto' ||
                                    style.overflowY === 'scroll'
                                ) {
                                    viewport = div;
                                    break;
                                }
                            }
                        }

                        if (viewport) {
                            // Force immediate scroll to bottom
                            viewport.scrollTop = viewport.scrollHeight;

                            // Also try smooth scroll as backup
                            if (viewport.scrollTo) {
                                viewport.scrollTo({
                                    top: viewport.scrollHeight,
                                    behavior: 'auto', // Use auto for more reliable scrolling during streaming
                                });
                            }
                        } else {
                            console.warn('Could not find scrollable viewport in ScrollArea');
                        }
                    } catch (error) {
                        console.warn('Auto-scroll failed:', error);
                    }
                }
            }
        });
    }, 50); // Throttle to 50ms for smooth scrolling
};

// Check if we should automatically start AI generation
const checkForAutoGeneration = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const shouldGenerate = urlParams.get('ai_generate') === 'true';
    const generationType = urlParams.get('generation_type');

    if (shouldGenerate && generationType) {
        // Small delay to ensure the UI is fully mounted
        setTimeout(() => {
            if (generationType === 'progressive' || generationType === 'single') {
                startStreamingGeneration(generationType);

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

onUnmounted(() => {
    clearAutoSave();
    if (eventSource.value) {
        eventSource.value.close();
    }
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
    }
    document.removeEventListener('keydown', handleKeydown);

    // Remove fullscreen listeners
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.removeEventListener('msfullscreenchange', handleFullscreenChange);
});

// Watch for content changes
watch(chapterContent, handleContentChange);
watch(chapterTitle, triggerAutoSave);
</script>

<template>
    <TooltipProvider>
        <!-- Chat Mode Layout -->
        <ChatModeLayout
            v-if="showChatMode"
            :project="memoizedProject"
            :chapter="memoizedChapter"
            :chapter-title="chapterTitle"
            :chapter-content="chapterContent"
            :current-word-count="currentWordCount"
            :target-word-count="targetWordCount"
            :progress-percentage="progressPercentage"
            :writing-quality-score="writingQualityScore"
            :is-valid="isValid"
            :is-saving="isSaving"
            :show-preview="showPreview"
            :is-generating="isGenerating"
            :generation-progress="generationProgress"
            :history-index="historyIndex"
            :content-history-length="contentHistory.length"
            :selected-text="selectedText"
            @update:chapter-title="chapterTitle = $event"
            @update:chapter-content="chapterContent = $event"
            @update:selected-text="selectedText = $event"
            @update:show-preview="showPreview = $event"
            @save="(autoSave) => saveChapter(autoSave)"
            @undo="handleUndo"
            @redo="handleRedo"
            @exit-chat-mode="exitChatMode"
        />

        <!-- Fullscreen Layout with Sidebars -->
        <div v-else-if="isNativeFullscreen" class="flex h-screen flex-col overflow-hidden bg-background">
            <!-- Header -->
            <div
                class="flex flex-shrink-0 items-center justify-between border-b bg-background/95 p-3 backdrop-blur supports-[backdrop-filter]:bg-background/60"
            >
                <div class="flex items-center gap-4">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button @click="router.visit(route('projects.show', props.project.slug))" variant="ghost" size="icon">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Back to Project</p>
                        </TooltipContent>
                    </Tooltip>

                    <div>
                        <h1 class="text-xl font-bold">{{ props.project.title }}</h1>
                        <p class="text-sm text-muted-foreground">
                            Chapter {{ props.chapter.chapter_number }} • {{ currentWordCount }} / {{ targetWordCount }} words
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Sidebar Toggle Buttons -->
                    <Button
                        @click="showLeftSidebarInFullscreen = !showLeftSidebarInFullscreen"
                        :variant="showLeftSidebarInFullscreen ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Menu class="mr-2 h-4 w-4" />
                        Chapters
                    </Button>

                    <Button
                        @click="showRightSidebarInFullscreen = !showRightSidebarInFullscreen"
                        :variant="showRightSidebarInFullscreen ? 'default' : 'outline'"
                        size="sm"
                    >
                        <Brain class="mr-2 h-4 w-4" />
                        AI Tools
                    </Button>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button @click="showStatistics = !showStatistics" :variant="showStatistics ? 'default' : 'outline'" size="sm">
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
                            <p>Toggle {{ isDarkMode ? 'Light' : 'Dark' }} Mode</p>
                        </TooltipContent>
                    </Tooltip>

                    <Separator orientation="vertical" class="h-6" />

                    <span class="text-sm text-muted-foreground">Press F11 or ESC to exit</span>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="flex-shrink-0 bg-muted/30 px-3 py-2">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium">Writing Progress</span>
                    <span class="text-sm text-muted-foreground"
                        >{{ currentWordCount }} / {{ targetWordCount }} words ({{ Math.round(progressPercentage) }}%)</span
                    >
                </div>
                <Progress :value="progressPercentage" class="h-1.5" />
            </div>

            <!-- AI Generation Progress Card -->
            <div v-if="isGenerating" class="mx-3 my-2">
                <div
                    class="relative overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-purple-50 p-3 shadow-sm dark:border-blue-800 dark:from-blue-950/30 dark:to-purple-950/30"
                >
                    <!-- Background Animation -->
                    <div class="absolute inset-0 -skew-x-12 animate-pulse bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>

                    <!-- Header -->
                    <div class="relative z-10 mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600">
                                    <Brain class="h-3 w-3 text-white" />
                                </div>
                                <!-- Pulsing ring -->
                                <div
                                    class="absolute inset-0 h-6 w-6 animate-ping rounded-full bg-gradient-to-br from-blue-500 to-purple-600 opacity-20"
                                ></div>
                            </div>
                            <div>
                                <h4 class="text-xs font-semibold text-blue-900 dark:text-blue-100">AI Generator</h4>
                                <p class="text-xs text-blue-700 dark:text-blue-300">{{ generationPhase }}</p>
                            </div>
                        </div>
                        <Badge variant="outline" class="border-blue-300 text-xs text-blue-700 dark:border-blue-700 dark:text-blue-300">
                            {{ Math.round(generationPercentage) }}%
                        </Badge>
                    </div>

                    <!-- Progress Bar -->
                    <div class="relative z-10 mb-2">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-xs text-blue-800 dark:text-blue-200">Generation Progress</span>
                            <span class="text-xs text-blue-600 dark:text-blue-400">{{ streamWordCount }} / {{ estimatedTotalWords }} words</span>
                        </div>
                        <div class="relative h-2 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900/50">
                            <!-- Animated progress bar -->
                            <div
                                class="absolute top-0 left-0 h-full rounded-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-500 ease-out"
                                :style="{ width: `${generationPercentage}%` }"
                            >
                                <!-- Shimmer effect -->
                                <div class="absolute inset-0 animate-pulse bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
                            </div>
                            <!-- Progress glow -->
                            <div
                                class="absolute top-0 left-0 h-full rounded-full bg-gradient-to-r from-blue-400 to-purple-400 opacity-50 blur-sm transition-all duration-500"
                                :style="{ width: `${generationPercentage}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Status Message -->
                    <div class="relative z-10 flex items-center gap-2">
                        <!-- Dynamic icon based on phase -->
                        <div class="flex-shrink-0">
                            <div v-if="generationPhase === 'Initializing'" class="h-2 w-2 animate-bounce rounded-full bg-blue-500"></div>
                            <div v-else-if="generationPhase === 'Connecting'" class="flex gap-0.5">
                                <div class="h-2 w-0.5 animate-pulse rounded-full bg-blue-500"></div>
                                <div class="h-2 w-0.5 animate-pulse rounded-full bg-purple-500" style="animation-delay: 0.2s"></div>
                                <div class="h-2 w-0.5 animate-pulse rounded-full bg-blue-500" style="animation-delay: 0.4s"></div>
                            </div>
                            <div
                                v-else-if="generationPhase === 'Generating'"
                                class="h-2 w-2 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                            ></div>
                            <div v-else-if="generationPhase === 'Complete'" class="h-2 w-2 rounded-full bg-green-500"></div>
                            <div v-else class="h-2 w-2 rounded-full bg-red-500"></div>
                        </div>
                        <p class="flex-1 text-xs text-blue-800 dark:text-blue-200">{{ generationProgress }}</p>
                    </div>
                </div>
            </div>

            <!-- Writing Statistics -->
            <WritingStatistics
                v-if="showStatistics"
                :show-statistics="showStatistics"
                :current-word-count="currentWordCount"
                :writing-stats="writingStats"
            />

            <!-- Main Content with Sidebars -->
            <div class="flex flex-1 overflow-hidden">
                <!-- Left Sidebar -->
                <div v-if="showLeftSidebarInFullscreen" class="w-80 flex-shrink-0 overflow-y-auto border-r bg-background/50 backdrop-blur">
                    <div class="p-4">
                        <Suspense>
                            <ChapterNavigation
                                :all-chapters="memoizedAllChapters"
                                :current-chapter="memoizedChapter"
                                :project="memoizedProject"
                                :current-word-count="currentWordCount"
                                :target-word-count="targetWordCount"
                                :writing-quality-score="writingQualityScore"
                                :chapter-content-length="chapterContent.length"
                                @go-to-chapter="goToChapter"
                                @generate-next-chapter="generateNextChapter"
                            />
                            <template #fallback>
                                <div class="flex items-center justify-center p-4">
                                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                                </div>
                            </template>
                        </Suspense>
                    </div>
                </div>

                <!-- Main Editor -->
                <div class="flex min-h-0 flex-1 flex-col">
                    <Card class="flex min-h-0 flex-1 flex-col rounded-none border-0 bg-transparent shadow-none">
                        <CardHeader class="flex-shrink-0 border-b px-4 py-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-lg">Chapter {{ chapter.chapter_number }}</CardTitle>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="chapter.status === 'approved' ? 'default' : 'secondary'">
                                        {{ chapter.status.replace('_', ' ') }}
                                    </Badge>
                                    <Badge variant="outline" class="text-xs"> {{ writingQualityScore }}% Quality </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="flex min-h-0 flex-1 flex-col space-y-3 p-4">
                            <!-- Chapter Title Input -->
                            <div class="flex-shrink-0 space-y-2">
                                <Label for="chapter-title-fs" class="text-sm font-medium">Chapter Title</Label>
                                <Input
                                    id="chapter-title-fs"
                                    v-model="chapterTitle"
                                    placeholder="Enter chapter title..."
                                    class="h-10 text-lg font-medium"
                                />
                            </div>

                            <!-- Content Editor -->
                            <div class="flex min-h-0 flex-1 flex-col space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label for="chapter-content-fs" class="text-sm font-medium">Content</Label>
                                    <div class="flex items-center gap-2">
                                        <Button @click="togglePresentationMode" :variant="showPresentationMode ? 'default' : 'outline'" size="sm">
                                            <Eye class="mr-1 h-4 w-4" />
                                            {{ showPresentationMode ? 'Edit Mode' : 'Preview Mode' }}
                                        </Button>
                                    </div>
                                </div>

                                <!-- Rich Text Editor -->
                                <ScrollArea ref="editorScrollRef" class="h-[calc(100vh-200px)] w-full" v-show="!showPresentationMode">
                                    <RichTextEditor
                                        v-model="chapterContent"
                                        placeholder="Start writing your chapter..."
                                        min-height="100%"
                                        class="text-base leading-relaxed"
                                    />
                                </ScrollArea>

                                <!-- Presentation View -->
                                <ScrollArea ref="previewScrollRef" class="h-[calc(100vh-200px)] w-full" v-show="showPresentationMode">
                                    <RichTextViewer
                                        :content="chapterContent"
                                        class="rounded-md border border-border/50 bg-background p-6"
                                        style="font-family: 'Times New Roman', serif; line-height: 1.8"
                                    />
                                </ScrollArea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-shrink-0 flex-row gap-2 pt-2">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="save(false)" :disabled="!isValid || isSaving" size="sm">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSaving ? 'Saving...' : 'Save Draft' }}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Save chapter as draft (Ctrl+S)</p>
                                    </TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="showPreview = !showPreview" variant="outline" size="sm">
                                            <Eye v-if="!showPreview" class="mr-2 h-4 w-4" />
                                            <PenTool v-else class="mr-2 h-4 w-4" />
                                            {{ showPreview ? 'Edit' : 'Preview' }}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ showPreview ? 'Switch to edit mode' : 'Switch to preview mode' }}</p>
                                    </TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="save(false)" :disabled="!isValid || currentWordCount < targetWordCount * 0.8" size="sm">
                                            <CheckCircle class="mr-2 h-4 w-4" />
                                            Save & Mark Complete
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Mark chapter as complete (requires 80% of target word count)</p>
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Sidebar -->
                <div v-if="showRightSidebarInFullscreen" class="w-80 flex-shrink-0 overflow-y-auto border-l bg-background/50 backdrop-blur">
                    <div class="space-y-6 p-4">
                        <Suspense>
                            <AISidebar
                                :project="memoizedProject"
                                :is-generating="isGenerating"
                                :selected-text="selectedText"
                                :is-loading-suggestions="isLoadingSuggestions"
                                :show-citation-helper="showCitationHelper"
                                :chapter-content="chapterContent"
                                @start-streaming-generation="startStreamingGeneration"
                                @get-ai-suggestions="getAISuggestions"
                                @update:show-citation-helper="showCitationHelper = $event"
                            />
                            <template #fallback>
                                <div class="flex items-center justify-center p-4">
                                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                                </div>
                            </template>
                        </Suspense>

                        <Suspense>
                            <DefensePreparationPanel :show-defense-prep="showDefensePrep" @update:show-defense-prep="showDefensePrep = $event" />
                            <template #fallback>
                                <div class="flex items-center justify-center p-4">
                                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-muted border-t-transparent"></div>
                                </div>
                            </template>
                        </Suspense>
                    </div>
                </div>
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
                                    <Button
                                        @click="router.visit(route('projects.show', props.project.slug))"
                                        variant="ghost"
                                        size="icon"
                                        class="hidden sm:flex"
                                    >
                                        <ArrowLeft class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Back to Project</p>
                                </TooltipContent>
                            </Tooltip>

                            <div>
                                <h1 class="text-xl font-bold sm:text-2xl">{{ props.project.title }}</h1>
                                <p class="text-sm text-muted-foreground">
                                    Chapter {{ props.chapter.chapter_number }} • {{ currentWordCount }} / {{ targetWordCount }} words
                                </p>
                            </div>
                        </div>

                        <!-- Mobile menu buttons -->
                        <div class="flex items-center gap-2">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button @click="showLeftSidebar = true" variant="outline" size="icon" class="lg:hidden">
                                        <Menu class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Chapter Navigation</p>
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button @click="showRightSidebar = true" variant="outline" size="icon" class="lg:hidden">
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
                                        <Button @click="toggleChatMode" :variant="showChatMode ? 'default' : 'outline'" size="sm">
                                            <MessageSquare class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ showChatMode ? 'Exit' : 'Open' }} AI Chat Assistant</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button @click="showStatistics = !showStatistics" :variant="showStatistics ? 'default' : 'outline'" size="sm">
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
                                        <Button @click="toggleNativeFullscreen" :variant="isNativeFullscreen ? 'default' : 'outline'" size="sm">
                                            <Maximize2 class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ isNativeFullscreen ? 'Exit' : 'Enter' }} Fullscreen Mode (F11)</p>
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="mb-6">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium">Writing Progress</span>
                            <span class="text-sm text-muted-foreground"
                                >{{ currentWordCount }} / {{ targetWordCount }} words ({{ Math.round(progressPercentage) }}%)</span
                            >
                        </div>
                        <Progress :value="progressPercentage" class="h-2" />
                    </div>

                    <!-- AI Generation Progress Card (Fullscreen) -->
                    <div v-if="isGenerating" class="mb-6">
                        <div
                            class="relative overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-purple-50 p-4 shadow-sm dark:border-blue-800 dark:from-blue-950/30 dark:to-purple-950/30"
                        >
                            <!-- Background Animation -->
                            <div
                                class="absolute inset-0 -skew-x-12 animate-pulse bg-gradient-to-r from-transparent via-white/10 to-transparent"
                            ></div>

                            <!-- Header -->
                            <div class="relative z-10 mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600"
                                        >
                                            <Brain class="h-4 w-4 text-white" />
                                        </div>
                                        <!-- Pulsing ring -->
                                        <div
                                            class="absolute inset-0 h-8 w-8 animate-ping rounded-full bg-gradient-to-br from-blue-500 to-purple-600 opacity-20"
                                        ></div>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100">AI Chapter Generator</h4>
                                        <p class="text-xs text-blue-700 dark:text-blue-300">{{ generationPhase }}</p>
                                    </div>
                                </div>
                                <Badge variant="outline" class="border-blue-300 text-xs text-blue-700 dark:border-blue-700 dark:text-blue-300">
                                    {{ Math.round(generationPercentage) }}%
                                </Badge>
                            </div>

                            <!-- Progress Bar -->
                            <div class="relative z-10 mb-3">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-xs text-blue-800 dark:text-blue-200">Generation Progress</span>
                                    <span class="text-xs text-blue-600 dark:text-blue-400"
                                        >{{ streamWordCount }} / {{ estimatedTotalWords }} words</span
                                    >
                                </div>
                                <div class="relative h-2 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900/50">
                                    <!-- Animated progress bar -->
                                    <div
                                        class="absolute top-0 left-0 h-full rounded-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-500 ease-out"
                                        :style="{ width: `${generationPercentage}%` }"
                                    >
                                        <!-- Shimmer effect -->
                                        <div
                                            class="absolute inset-0 animate-pulse bg-gradient-to-r from-transparent via-white/30 to-transparent"
                                        ></div>
                                    </div>
                                    <!-- Progress glow -->
                                    <div
                                        class="absolute top-0 left-0 h-full rounded-full bg-gradient-to-r from-blue-400 to-purple-400 opacity-50 blur-sm transition-all duration-500"
                                        :style="{ width: `${generationPercentage}%` }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Status Message -->
                            <div class="relative z-10 flex items-center gap-2">
                                <!-- Dynamic icon based on phase -->
                                <div class="flex-shrink-0">
                                    <div v-if="generationPhase === 'Initializing'" class="h-3 w-3 animate-bounce rounded-full bg-blue-500"></div>
                                    <div v-else-if="generationPhase === 'Connecting'" class="flex gap-1">
                                        <div class="h-3 w-1 animate-pulse rounded-full bg-blue-500"></div>
                                        <div class="h-3 w-1 animate-pulse rounded-full bg-purple-500" style="animation-delay: 0.2s"></div>
                                        <div class="h-3 w-1 animate-pulse rounded-full bg-blue-500" style="animation-delay: 0.4s"></div>
                                    </div>
                                    <div
                                        v-else-if="generationPhase === 'Generating'"
                                        class="h-3 w-3 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                                    ></div>
                                    <div v-else-if="generationPhase === 'Complete'" class="h-3 w-3 rounded-full bg-green-500"></div>
                                    <div v-else class="h-3 w-3 rounded-full bg-red-500"></div>
                                </div>
                                <p class="flex-1 text-xs text-blue-800 dark:text-blue-200">{{ generationProgress }}</p>
                            </div>

                            <!-- Quality indicator -->
                            <div
                                v-if="generationPhase === 'Generating' && streamWordCount > 50"
                                class="relative z-10 mt-3 border-t border-blue-200 pt-3 dark:border-blue-800"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-blue-700 dark:text-blue-300">Writing Quality</span>
                                    <div class="flex items-center gap-1">
                                        <div class="flex gap-0.5">
                                            <div
                                                v-for="i in 5"
                                                :key="i"
                                                class="h-2 w-2 rounded-full"
                                                :class="i <= Math.ceil(writingQualityScore / 20) ? 'bg-yellow-400' : 'bg-blue-200 dark:bg-blue-800'"
                                            ></div>
                                        </div>
                                        <span class="ml-1 text-blue-600 dark:text-blue-400">{{ writingQualityScore }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Writing Statistics -->
                    <WritingStatistics :show-statistics="showStatistics" :current-word-count="currentWordCount" :writing-stats="writingStats" />

                    <!-- Main Content Grid -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        <!-- Left Sidebar (Desktop) -->
                        <div class="hidden space-y-6 lg:col-span-2 lg:block">
                            <ChapterNavigation
                                :all-chapters="memoizedAllChapters"
                                :current-chapter="memoizedChapter"
                                :project="memoizedProject"
                                :current-word-count="currentWordCount"
                                :target-word-count="targetWordCount"
                                :writing-quality-score="writingQualityScore"
                                :chapter-content-length="chapterContent.length"
                                @go-to-chapter="goToChapter"
                                @generate-next-chapter="generateNextChapter"
                            />
                        </div>

                        <!-- Main Editor -->
                        <div class="lg:col-span-7">
                            <Card class="border-[0.5px] border-border/50">
                                <CardHeader class="pb-4">
                                    <div class="flex items-center justify-between">
                                        <CardTitle class="text-lg">Chapter {{ chapter.chapter_number }}</CardTitle>
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="chapter.status === 'approved' ? 'default' : 'secondary'">
                                                {{ chapter.status.replace('_', ' ') }}
                                            </Badge>
                                            <Badge variant="outline" class="text-xs"> {{ writingQualityScore }}% Quality </Badge>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent class="space-y-4">
                                    <!-- Chapter Title Input -->
                                    <div class="space-y-2">
                                        <Label for="chapter-title">Chapter Title</Label>
                                        <Input
                                            id="chapter-title"
                                            v-model="chapterTitle"
                                            placeholder="Enter chapter title..."
                                            class="text-lg font-medium"
                                        />
                                    </div>

                                    <!-- Content Editor -->
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <Label for="chapter-content">Content</Label>
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    @click="togglePresentationMode"
                                                    :variant="showPresentationMode ? 'default' : 'outline'"
                                                    size="sm"
                                                >
                                                    <Eye class="mr-1 h-4 w-4" />
                                                    {{ showPresentationMode ? 'Edit Mode' : 'Preview Mode' }}
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Rich Text Editor -->
                                        <ScrollArea
                                            ref="editorFullscreenScrollRef"
                                            class="h-[calc(100vh-150px)] w-full"
                                            v-show="!showPresentationMode"
                                        >
                                            <RichTextEditor
                                                v-model="chapterContent"
                                                placeholder="Start writing your chapter..."
                                                min-height="600px"
                                                class="text-base leading-relaxed"
                                            />
                                        </ScrollArea>

                                        <!-- Presentation View -->
                                        <ScrollArea
                                            ref="previewFullscreenScrollRef"
                                            class="h-[calc(100vh-150px)] w-full"
                                            v-show="showPresentationMode"
                                        >
                                            <RichTextViewer
                                                :content="chapterContent"
                                                class="min-h-[600px] rounded-md border border-border/50 bg-background p-6"
                                                style="font-family: 'Times New Roman', serif; line-height: 1.8"
                                            />
                                        </ScrollArea>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-col gap-2 pt-4 sm:flex-row">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button @click="save(false)" :disabled="!isValid || isSaving" size="sm" class="flex-1 sm:flex-none">
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
                                                <Button @click="showPreview = !showPreview" variant="outline" size="sm" class="flex-1 sm:flex-none">
                                                    <Eye v-if="!showPreview" class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <PenTool v-else class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <span class="hidden sm:inline">{{ showPreview ? 'Edit' : 'Preview' }}</span>
                                                    <span class="sm:hidden">{{ showPreview ? 'Edit' : 'Preview' }}</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ showPreview ? 'Switch to edit mode' : 'Switch to preview mode' }}</p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    @click="save(false)"
                                                    :disabled="!isValid || currentWordCount < targetWordCount * 0.8"
                                                    size="sm"
                                                    class="flex-1 sm:flex-none"
                                                >
                                                    <CheckCircle class="mr-1 h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
                                                    <span class="hidden sm:inline">Save & Mark Complete</span>
                                                    <span class="sm:hidden">Complete</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Mark chapter as complete (requires 80% of target word count)</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Right Sidebar (Desktop) -->
                        <div class="hidden space-y-4 sm:space-y-6 lg:col-span-3 lg:block">
                            <AISidebar
                                :project="memoizedProject"
                                :is-generating="isGenerating"
                                :selected-text="selectedText"
                                :is-loading-suggestions="isLoadingSuggestions"
                                :show-citation-helper="showCitationHelper"
                                :chapter-content="chapterContent"
                                @start-streaming-generation="startStreamingGeneration"
                                @get-ai-suggestions="getAISuggestions"
                                @update:show-citation-helper="showCitationHelper = $event"
                            />

                            <DefensePreparationPanel :show-defense-prep="showDefensePrep" @update:show-defense-prep="showDefensePrep = $event" />
                        </div>
                    </div>

                    <!-- Mobile Overlays -->
                    <MobileNavOverlay
                        :show-left-sidebar="showLeftSidebar"
                        :show-right-sidebar="showRightSidebar"
                        :is-mobile="isMobile"
                        :all-chapters="memoizedAllChapters"
                        :current-chapter="memoizedChapter"
                        :project="memoizedProject"
                        :current-word-count="currentWordCount"
                        :target-word-count="targetWordCount"
                        :writing-quality-score="writingQualityScore"
                        :chapter-content-length="chapterContent.length"
                        :is-generating="isGenerating"
                        :selected-text="selectedText"
                        :is-loading-suggestions="isLoadingSuggestions"
                        :show-citation-helper="showCitationHelper"
                        :chapter-content="chapterContent"
                        @update:show-left-sidebar="showLeftSidebar = $event"
                        @update:show-right-sidebar="showRightSidebar = $event"
                        @go-to-chapter="goToChapter"
                        @generate-next-chapter="generateNextChapter"
                        @start-streaming-generation="startStreamingGeneration"
                        @get-ai-suggestions="getAISuggestions"
                        @update:show-citation-helper="showCitationHelper = $event"
                    />
                </div>
            </div>
        </AppLayout>
    </TooltipProvider>
</template>
