<!-- /resources/js/pages/projects/Writing.vue -->
<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Activity, ArrowLeft, ArrowRight, BookOpen, Brain, Clock, Edit, FileText, Play, Target, Zap, Sparkles, AlertTriangle, Check } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import PurchaseModal from '@/components/PurchaseModal.vue';
import { useWordBalance } from '@/composables/useWordBalance';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    target_word_count: number | null;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
    updated_at: string;
}

interface Outline {
    id: number;
    chapter_number: number;
    chapter_title: string;
    target_word_count: number | null;
    completion_threshold: number | null;
}

interface FacultyStructureChapter {
    chapter_number: number;
    chapter_title: string;
    target_word_count: number | null;
    completion_threshold: number | null;
    is_required: boolean;
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
    chapters: Chapter[];
    outlines?: Outline[];
    facultyStructureChapters?: FacultyStructureChapter[];
    progress?: number;
    project_category_id?: number;
    category?: ProjectCategory;
}

interface Props {
    project: Project;
    targetWordCount: number;
    estimatedChapters: number;
}

const props = defineProps<Props>();

const activeTab = ref('overview');
const isGenerating = ref(false);
const generationType = ref<'progressive' | 'bulk'>('progressive');
const isTogglingMode = ref(false);
const currentMode = ref(props.project.mode);

// Watch for prop changes to sync currentMode with server state
watch(() => props.project.mode, (newMode) => {
    currentMode.value = newMode;
}, { immediate: true });

// Dialog state for mode confirmation
const showModeConfirmDialog = ref(false);
const pendingNewMode = ref<'auto' | 'manual'>('auto');

// Dialog state for bulk generation confirmation
const showBulkGenerationDialog = ref(false);
const showTopicApprovalDialog = ref(false);

// Word balance guard for AI actions
const {
    wordBalance,
    balance,
    showPurchaseModal,
    requiredWordsForModal,
    actionDescriptionForModal,
    checkAndPrompt,
    closePurchaseModal,
    estimates,
} = useWordBalance();

const maxAllowedChapters = computed(() => {
    const structureChapterCount = props.project.facultyStructureChapters?.length || 0;

    return structureChapterCount
        || props.project.category?.default_chapter_count
        || props.estimatedChapters
        || props.project.chapters.length
        || 1;
});

const averageChapterTarget = computed(() => {
    const chapterCount = maxAllowedChapters.value || 1;
    const totalTarget = props.targetWordCount || 0;

    // If we don't have a project-level target, fall back to faculty structure average
    const structureTargets = (props.project.facultyStructureChapters || []).map((c) => c.target_word_count || 0);
    const structureAverage = structureTargets.length > 0
        ? Math.ceil(structureTargets.reduce((sum, val) => sum + val, 0) / structureTargets.length)
        : 0;

    if (!totalTarget) {
        return structureAverage || 2000; // Conservative default when we cannot infer a target
    }

    return Math.ceil(totalTarget / chapterCount);
});

// Debug project mode on load


// Regular ref for Switch state - will be manually synced
const isAutoMode = ref(currentMode.value === 'auto');

// Watch currentMode changes and update switch state
watch(currentMode, (newMode) => {
    isAutoMode.value = newMode === 'auto';
}, { immediate: true });

// Handle switch toggle clicks
const handleSwitchToggle = () => {
    if (isTogglingMode.value) {
        return;
    }

    const newMode = currentMode.value === 'auto' ? 'manual' : 'auto';

    // Set pending mode and show confirmation dialog
    pendingNewMode.value = newMode;
    showModeConfirmDialog.value = true;
};

console.log('🔍 INITIAL TOGGLE STATE:', {
    currentMode: currentMode.value,
    isAutoMode: isAutoMode.value,
    shouldBeChecked: currentMode.value === 'auto'
});

/**
 * COMPUTED PROPERTIES FOR PROGRESS TRACKING
 * Calculates writing progress and chapter completion
 */
const totalWordCount = computed(() => {
    return props.project.chapters.reduce((total, chapter) => total + chapter.word_count, 0);
});

const progressPercentage = computed(() => {
    if (props.project.progress !== undefined) {
        return props.project.progress;
    }
    return Math.min((totalWordCount.value / props.targetWordCount) * 100, 100);
});

const completedChapters = computed(() => {
    return props.project.chapters.filter((chapter) => chapter.status !== 'not_started' && chapter.word_count > 0).length;
});

const nextChapterNumber = computed(() => {
    const limit = maxAllowedChapters.value;

    // Find the first chapter that is not started or has no content
    const firstUnstartedChapter = props.project.chapters.find(
        (c) => c.status === 'not_started' || c.content === null || c.content === '' || c.content === undefined,
    );

    if (firstUnstartedChapter) {
        // Only return it if it doesn't exceed the category limit
        return firstUnstartedChapter.chapter_number <= limit ? firstUnstartedChapter.chapter_number : null;
    }

    // If all existing chapters are started, return next number only if within limits
    const maxChapterNumber = Math.max(...props.project.chapters.map((c) => c.chapter_number), 0);
    const nextNumber = maxChapterNumber + 1;

    console.log('nextChapterNumber debug (Writing page):', {
        maxAllowedChapters: limit,
        categoryName: props.project.category?.name,
        maxChapterNumber,
        nextNumber,
        withinLimits: nextNumber <= limit,
    });

    return nextNumber <= limit ? nextNumber : null;
});

const getTargetWordsForChapter = (chapterNumber?: number): number => {
    const targetChapterNumber = chapterNumber ?? nextChapterNumber.value;
    const matchingChapter = targetChapterNumber
        ? props.project.chapters.find((c) => c.chapter_number === targetChapterNumber)
        : null;

    if (matchingChapter?.target_word_count) {
        return matchingChapter.target_word_count;
    }

    const matchingOutline = targetChapterNumber
        ? (props.project.outlines || []).find((outline) => outline.chapter_number === targetChapterNumber)
        : null;

    if (matchingOutline?.target_word_count) {
        return matchingOutline.target_word_count;
    }

    const facultyTemplate = targetChapterNumber
        ? (props.project.facultyStructureChapters || []).find((template) => template.chapter_number === targetChapterNumber)
        : null;

    if (facultyTemplate?.target_word_count) {
        return facultyTemplate.target_word_count;
    }

    return averageChapterTarget.value;
};

const canGenerateMoreChapters = computed(() => {
    const currentMaxChapter = Math.max(...props.project.chapters.map((c) => c.chapter_number), 0);
    return currentMaxChapter < maxAllowedChapters.value && nextChapterNumber.value !== null;
});

/**
 * RECENT ACTIVITY TRACKING
 * Find the most recently updated chapter for "Continue Writing"
 */
const lastWorkedChapter = computed(() => {
    const chaptersWithContent = props.project.chapters.filter((c) => c.content && c.content.trim() !== '' && c.updated_at);

    if (chaptersWithContent.length === 0) return null;

    return chaptersWithContent.reduce((latest, current) => (new Date(current.updated_at) > new Date(latest.updated_at) ? current : latest));
});

const timeSinceLastUpdate = computed(() => {
    if (!lastWorkedChapter.value) return null;

    const lastUpdate = new Date(lastWorkedChapter.value.updated_at);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - lastUpdate.getTime()) / (1000 * 60 * 60));

    if (diffInHours < 1) return 'Less than an hour ago';
    if (diffInHours < 24) return `${diffInHours} hours ago`;
    if (diffInHours < 48) return 'Yesterday';
    const days = Math.floor(diffInHours / 24);
    return `${days} days ago`;
});

onMounted(() => {
    // Auto-select appropriate tab based on writing mode
    activeTab.value = currentMode.value === 'auto' ? 'ai-generation' : 'manual-writing';
});

const estimateGenerationCost = (type: 'single' | 'progressive' | 'bulk', chapterNumber?: number): number => {
    const targetWords = getTargetWordsForChapter(chapterNumber);
    const base = estimates.chapter(targetWords);

    if (type === 'bulk') {
        const remainingChapters = Math.max(1, maxAllowedChapters.value - completedChapters.value);
        return base * remainingChapters;
    }

    return base;
};

/**
 * AI CHAPTER GENERATION - AUTO MODE
 * Opens the editor with streaming AI generation
 */
const generateChapter = (type: 'single' | 'progressive' | 'bulk', specificChapter?: number) => {
    const estimatedWords = estimateGenerationCost(type, specificChapter);
    const actionLabel = type === 'bulk'
        ? 'bulk-generate chapters'
        : type === 'progressive'
            ? 'stream the chapter with AI'
            : 'generate the chapter with AI';

    if (!checkAndPrompt(estimatedWords, actionLabel)) {
        return;
    }

    if (type === 'bulk') {
        // Show bulk generation confirmation dialog
        showBulkGenerationDialog.value = true;
        return;
    }

    // Use specific chapter number if provided, otherwise use next available
    const chapterNumber = specificChapter || nextChapterNumber.value;
    if (!chapterNumber) {
        toast('Chapter Limit Reached', {
            description: `You have reached the maximum number of chapters (${maxAllowedChapters.value}) for this project category.`,
        });
        return;
    }

    // Check if chapter already has content
    const existingChapter = props.project.chapters.find(c => c.chapter_number === chapterNumber);
    if (existingChapter && existingChapter.content && existingChapter.content.trim() !== '') {
        if (!confirm(`Chapter ${chapterNumber} already has content. Do you want to regenerate it?`)) {
            return;
        }
    }

    // For single/progressive generation, navigate to the chapter write mode with AI streaming
    router.visit(
        route('chapters.write', {
            project: props.project.slug,
            chapter: chapterNumber,
        }) +
        '?ai_generate=true&generation_type=' +
        type,
    );
};

/**
 * NAVIGATE TO MANUAL EDITOR
 * Opens full-featured editor for specific chapter
 */
const editChapter = (chapterNumber: number) => {
    router.visit(
        route('chapters.edit', {
            project: props.project.slug,
            chapter: chapterNumber,
        }),
    );
};

/**
 * NAVIGATE TO APPROPRIATE CHAPTER MODE
 * Uses write mode for new chapters, edit mode for existing ones
 * For manual mode projects, routes to the manual editor
 */
const goToChapter = (chapterNumber: number) => {
    const chapter = props.project.chapters.find(c => c.chapter_number === chapterNumber);
    const hasContent = chapter?.content && chapter.content.trim() !== '';

    // Check if project is in manual mode
    if (props.project.mode === 'manual' && chapter) {
        // Route to manual editor for manual mode projects
        router.visit(
            route('projects.manual-editor.show', {
                project: props.project.slug,
                chapter: chapter.chapter_number,
            }),
        );
        return;
    }

    if (hasContent) {
        // Chapter has content, use edit mode
        router.visit(
            route('chapters.edit', {
                project: props.project.slug,
                chapter: chapterNumber,
            }),
        );
    } else {
        // Chapter is new or empty, use write mode
        router.visit(
            route('chapters.write', {
                project: props.project.slug,
                chapter: chapterNumber,
            }),
        );
    }
};

/**
 * START NEW CHAPTER
 * Routes to manual editor for manual mode, write mode for auto mode
 */
const startChapter = (chapterNumber: number) => {
    console.log('🚀 START CHAPTER CLICKED', {
        chapterNumber,
        projectMode: props.project.mode,
        projectSlug: props.project.slug,
    });

    // Check if project is in manual mode
    if (props.project.mode === 'manual') {
        console.log('📝 Manual mode detected, routing to manual editor...');

        // Route directly to manual editor - it will create the chapter if it doesn't exist
        const manualEditorRoute = route('projects.manual-editor.show', {
            project: props.project.slug,
            chapter: chapterNumber,
        });

        console.log('✅ Routing to manual editor:', manualEditorRoute);
        router.visit(manualEditorRoute);
        return;
    }

    // For auto mode, use the chapters.write route
    const writeRoute = route('chapters.write', {
        project: props.project.slug,
        chapter: chapterNumber,
    });
    console.log('📄 Auto mode: Routing to chapters.write:', writeRoute);

    router.visit(writeRoute);
};

/**
 * GET CHAPTER STATUS STYLING
 * Returns appropriate badge styling for chapter status
 */
const getChapterStatusBadge = (status: string) => {
    switch (status) {
        case 'approved':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'in_review':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'draft':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        default:
            return 'bg-gray-100 text-gray-600 border-gray-200';
    }
};

/**
 * DEFAULT CHAPTER TITLES
 * Standard academic chapter structure
 */
const getDefaultChapterTitle = (chapterNumber: number): string => {
    const titles: Record<number, string> = {
        1: 'Introduction',
        2: 'Literature Review',
        3: 'Methodology',
        4: 'Design and Implementation',
        5: 'Results and Analysis',
        6: 'Conclusion and Recommendations',
    };
    return titles[chapterNumber] || `Chapter ${chapterNumber}`;
};

/**
 * CONFIRM MODE CHANGE
 * Called when user confirms the mode change in the dialog
 */
const confirmModeChange = () => {
    showModeConfirmDialog.value = false;
    toggleWritingMode();
};

/**
 * CONFIRM BULK GENERATION
 * Called when user confirms the bulk generation in the dialog
 */
const confirmBulkGeneration = () => {
    showBulkGenerationDialog.value = false;
    // Navigate to bulk generation page
    router.visit(route('projects.bulk-generate', props.project.slug) + '?start=true');
};

/**
 * CONFIRM TOPIC APPROVAL RESET
 * Called when user confirms going back to topic approval
 */
const confirmTopicApprovalReset = () => {
    showTopicApprovalDialog.value = false;
    try {
        // Use Inertia router for better CSRF handling
        router.post(
            route('projects.go-back-to-topic-approval', props.project.slug),
            {},
            {
                onSuccess: () => {
                    toast('Success', {
                        description: 'Returned to topic approval',
                    });
                },
                onError: () => {
                    toast('Error', {
                        description: 'Failed to go back. Please try again.',
                    });
                },
            },
        );
    } catch {
        toast('Error', {
            description: 'Failed to go back. Please try again.',
        });
    }
};

/**
 * TOGGLE WRITING MODE
 * Switch between Auto (AI Assisted) and Manual writing modes
 */
const toggleWritingMode = async () => {
    const newMode = pendingNewMode.value;
    const modeNames = { auto: 'AI Assisted', manual: 'Manual' };

    console.log('🔄 TOGGLE - Function called', { currentMode: currentMode.value, newMode });
    console.log('🔄 TOGGLE - Mode change', { from: currentMode.value, to: newMode });
    console.log('🔄 TOGGLE - User confirmed, starting request');

    isTogglingMode.value = true;

    try {
        const routeUrl = route('projects.update-mode', props.project.slug);
        console.log('🔄 TOGGLE - Route URL:', routeUrl);

        router.patch(
            routeUrl,
            { mode: newMode },
            {
                onSuccess: (page) => {
                    console.log('🔄 TOGGLE - Success response:', page);

                    // Update the current mode with the new value
                    currentMode.value = newMode;

                    // Show success message
                    const flashMessage = (page.props?.flash as any)?.message || `Switched to ${modeNames[newMode]} writing mode`;
                    toast('Success', {
                        description: flashMessage,
                    });

                    // Update active tab based on new mode
                    activeTab.value = newMode === 'auto' ? 'ai-generation' : 'manual-writing';

                    console.log('🔄 TOGGLE - UI Updated:', {
                        newMode,
                        currentMode: currentMode.value,
                        isAutoMode: isAutoMode.value,
                        activeTab: activeTab.value
                    });
                },
                onError: (errors) => {
                    console.error('🔄 TOGGLE - Error response:', errors);
                    toast('Error', {
                        description: 'Failed to switch writing mode. Please try again.',
                    });
                },
                onFinish: () => {
                    console.log('🔄 TOGGLE - Request finished');
                    isTogglingMode.value = false;
                },
            }
        );
    } catch (error) {
        console.error('🔄 TOGGLE - Exception:', error);
        toast('Error', {
            description: 'Failed to switch writing mode. Please try again.',
        });
        isTogglingMode.value = false;
    }
};

/**
 * GO BACK TO TOPIC APPROVAL
 * Allows users to modify their topic approval status
 */
// const goBackToTopicApproval = () => {
//     showTopicApprovalDialog.value = true;
// };
</script>

<template>
    <AppLayout :title="`Writing: ${project.title}`">
        <div class="mx-auto max-w-7xl space-y-8 p-6">
            <!-- Back Navigation -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <!-- <Button @click="goBackToTopicApproval" variant="ghost" size="sm"
                        class="text-muted-foreground hover:text-foreground transition-colors">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Topic Approval
                    </Button> -->
                </div>
                <Button @click="router.visit(route('projects.edit', project.slug))" variant="outline" size="sm">
                    <Edit class="mr-2 h-4 w-4" />
                    Edit Project Details
                </Button>
            </div>

            <!-- Hero Header -->
            <div
                class="relative overflow-hidden rounded-3xl border border-border/50 bg-gradient-to-b from-background to-muted/20 p-8 md:p-12">
                <div class="absolute inset-0 bg-grid-primary/5 [mask-image:linear-gradient(0deg,transparent,black)]" />
                <div class="relative z-10 flex flex-col items-center text-center space-y-6">
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <Badge variant="secondary" class="px-3 py-1 text-sm backdrop-blur-sm">
                            {{ project.type }}
                        </Badge>
                        <Badge :variant="currentMode === 'auto' ? 'default' : 'outline'"
                            :class="currentMode === 'auto' ? 'bg-blue-600 hover:bg-blue-700' : ''">
                            <component :is="currentMode === 'auto' ? Brain : Edit" class="mr-1.5 h-3.5 w-3.5" />
                            {{ currentMode === 'auto' ? 'AI Assisted' : 'Manual Writing' }}
                        </Badge>
                    </div>

                    <h1
                        class="max-w-4xl text-2xl font-bold tracking-tight text-foreground sm:text-3xl md:text-4xl bg-clip-text text-transparent bg-gradient-to-r from-foreground to-foreground/70">
                        {{ project.title }}
                    </h1>

                    <!-- Mode Toggle -->
                    <div
                        class="flex items-center gap-1 sm:gap-2 rounded-full border border-border/50 bg-background/50 p-1 sm:p-1.5 shadow-sm backdrop-blur-sm">
                        <button v-for="mode in ['auto', 'manual']" :key="mode"
                            @click="mode !== currentMode && handleSwitchToggle()"
                            class="relative flex items-center justify-center gap-1.5 sm:gap-2 rounded-full px-3 py-2 sm:px-6 sm:py-2.5 text-xs sm:text-sm font-medium transition-all duration-300 whitespace-nowrap"
                            :class="currentMode === mode
                                ? 'bg-primary text-primary-foreground shadow-md'
                                : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'">
                            <component :is="mode === 'auto' ? Brain : Edit" class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                            {{ mode === 'auto' ? 'AI Mode' : 'Manual Mode' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Content Column -->
                <div class="space-y-8 lg:col-span-2 order-2 lg:order-1">
                    <!-- Continue Writing Card -->
                    <div v-if="lastWorkedChapter"
                        class="group relative overflow-hidden rounded-2xl border border-border/50 bg-gradient-to-br from-amber-50/50 to-orange-50/50 p-6 dark:from-amber-950/10 dark:to-orange-950/10 transition-all hover:shadow-md">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-500">
                                    <Activity class="h-4 w-4" />
                                    <span class="text-sm font-medium">Continue where you left off</span>
                                </div>
                                <h3 class="text-xl font-semibold tracking-tight">{{ lastWorkedChapter.title }}</h3>
                                <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                    <span class="flex items-center gap-1.5">
                                        <Clock class="h-3.5 w-3.5" />
                                        {{ timeSinceLastUpdate }}
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <FileText class="h-3.5 w-3.5" />
                                        {{ lastWorkedChapter.word_count.toLocaleString() }} words
                                    </span>
                                </div>
                            </div>
                            <Button @click="startChapter(lastWorkedChapter.chapter_number)" size="lg"
                                class="shrink-0 bg-gradient-to-r from-amber-600 to-orange-600 text-white shadow-lg shadow-orange-500/20 transition-all hover:scale-105 hover:shadow-orange-500/30 hover:from-amber-700 hover:to-orange-700">
                                <Play class="mr-2 h-4 w-4 fill-current" />
                                Resume Writing
                            </Button>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- Start Writing -->
                        <button @click="nextChapterNumber ? startChapter(nextChapterNumber) : null"
                            :disabled="!canGenerateMoreChapters"
                            class="group relative flex flex-col items-start gap-4 rounded-2xl border border-border/50 bg-card p-6 text-left shadow-sm transition-all hover:border-primary/50 hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                            <div
                                class="rounded-full bg-green-100 p-3 text-green-600 dark:bg-green-900/20 dark:text-green-400">
                                <Edit class="h-6 w-6" />
                            </div>
                            <div>
                                <h3 class="font-semibold">Start Writing</h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ canGenerateMoreChapters ? `Begin Chapter ${nextChapterNumber} manually` : 'All chapters created' }}
                                </p>
                            </div>
                            <div
                                class="mt-auto flex items-center text-sm font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                Get started
                                <ArrowRight class="ml-1 h-4 w-4" />
                            </div>
                        </button>

                        <!-- Generate Chapter -->
                        <button v-if="currentMode === 'auto'" @click="generateChapter('progressive')"
                            :disabled="isGenerating || !canGenerateMoreChapters"
                            class="group relative flex flex-col items-start gap-4 rounded-2xl border border-border/50 bg-card p-6 text-left shadow-sm transition-all hover:border-purple-500/50 hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                            <div
                                class="rounded-full bg-purple-100 p-3 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400">
                                <Brain class="h-6 w-6" />
                            </div>
                            <div>
                                <h3 class="font-semibold">Generate Chapter</h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ isGenerating ? 'Generating content...' : 'Let AI draft the next chapter' }}
                                </p>
                            </div>
                            <div
                                class="mt-auto flex items-center text-sm font-medium text-purple-600 opacity-0 transition-opacity group-hover:opacity-100">
                                Start generation
                                <ArrowRight class="ml-1 h-4 w-4" />
                            </div>
                        </button>

                        <!-- Manual Mode Info (if manual) -->
                        <div v-else
                            class="flex flex-col items-start gap-4 rounded-2xl border border-border/50 bg-muted/30 p-6 text-left">
                            <div class="rounded-full bg-gray-100 p-3 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                <BookOpen class="h-6 w-6" />
                            </div>
                            <div>
                                <h3 class="font-semibold">Manual Mode Active</h3>
                                <p class="text-sm text-muted-foreground">Switch to AI mode to enable generation
                                    features.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Writing Mode Tabs -->
                    <Tabs v-model="activeTab" class="space-y-8">
                        <TabsList
                            class="flex h-auto w-full flex-col items-stretch justify-center rounded-2xl bg-muted/30 p-1.5 text-muted-foreground md:inline-flex md:h-14 md:w-auto md:flex-row md:items-center md:rounded-full border border-border/40 backdrop-blur-sm">
                            <TabsTrigger value="overview"
                                class="rounded-xl px-8 py-2.5 text-sm font-medium transition-all duration-300 data-[state=active]:bg-gray-200 data-[state=active]:text-gray-900 dark:data-[state=active]:bg-neutral-800 dark:data-[state=active]:text-gray-50 data-[state=active]:shadow-sm md:rounded-full md:py-0">
                                Overview
                            </TabsTrigger>
                            <TabsTrigger value="ai-generation" v-if="currentMode === 'auto'"
                                class="rounded-xl px-8 py-2.5 text-sm font-medium transition-all duration-300 data-[state=active]:bg-gray-200 data-[state=active]:text-gray-900 dark:data-[state=active]:bg-neutral-800 dark:data-[state=active]:text-gray-50 data-[state=active]:shadow-sm md:rounded-full md:py-0">
                                AI Generation
                            </TabsTrigger>
                            <TabsTrigger value="manual-writing"
                                class="rounded-xl px-8 py-2.5 text-sm font-medium transition-all duration-300 data-[state=active]:bg-gray-200 data-[state=active]:text-gray-900 dark:data-[state=active]:bg-neutral-800 dark:data-[state=active]:text-gray-50 data-[state=active]:shadow-sm md:rounded-full md:py-0">
                                {{ currentMode === 'auto' ? 'Edit Chapters' : 'Manual Writing' }}
                            </TabsTrigger>
                        </TabsList>

                        <!-- Overview Tab -->
                        <TabsContent value="overview" class="space-y-6">
                            <div class="grid gap-4">
                                <Card v-for="chapter in project.chapters" :key="chapter.id"
                                    class="group overflow-hidden border border-border/50 shadow-sm transition-all hover:border-primary/20 hover:shadow-md">
                                    <CardHeader class="pb-3">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <CardTitle class="text-lg group-hover:text-primary transition-colors">{{
                                                    chapter.title }}</CardTitle>
                                                <CardDescription>Chapter {{ chapter.chapter_number }}</CardDescription>
                                            </div>
                                            <Badge :class="getChapterStatusBadge(chapter.status)" variant="outline">
                                                {{ chapter.status.replace('_', ' ') }}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm text-muted-foreground">{{
                                                chapter.word_count.toLocaleString() }} words</div>
                                            <div class="flex gap-2">
                                                <Button
                                                    v-if="currentMode === 'auto' && (!chapter.content || chapter.content.trim() === '')"
                                                    @click="generateChapter('progressive', chapter.chapter_number)"
                                                    size="sm"
                                                    class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white shadow-sm">
                                                    <Brain class="mr-1 h-4 w-4" />
                                                    Generate
                                                </Button>

                                                <Button v-if="chapter.content && chapter.content.trim() !== ''"
                                                    @click="editChapter(chapter.chapter_number)" size="sm"
                                                    variant="outline" class="hover:bg-muted">
                                                    <Edit class="mr-1 h-4 w-4" />
                                                    Edit
                                                </Button>

                                                <Button v-else-if="currentMode === 'manual' || !currentMode"
                                                    @click="startChapter(chapter.chapter_number)" size="sm"
                                                    variant="default">
                                                    <Edit class="mr-1 h-4 w-4" />
                                                    Start
                                                </Button>

                                                <Badge v-else variant="outline" class="text-xs text-muted-foreground">
                                                    Not Started </Badge>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <div v-if="project.chapters.length === 0"
                                    class="rounded-xl border-2 border-dashed border-border/50 bg-muted/10 py-12 text-center">
                                    <BookOpen class="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
                                    <p class="mb-4 text-sm text-muted-foreground">
                                        No chapters created yet. {{ currentMode === 'auto' ? 'Use AI generation to get started.' : 'Create your first chapter manually.' }}
                                    </p>
                                </div>
                            </div>
                        </TabsContent>

                        <!-- AI Generation Tab -->
                        <TabsContent v-if="currentMode === 'auto'" value="ai-generation" class="space-y-8">
                            <Card
                                class="border-border/40 bg-card/50 backdrop-blur-xl shadow-xl shadow-primary/5 overflow-hidden">
                                <CardHeader class="border-b border-border/40 bg-muted/10 pb-8">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="p-3 rounded-xl bg-gradient-to-br from-yellow-500/20 to-orange-500/20 ring-1 ring-yellow-500/30">
                                            <Zap class="h-6 w-6 text-yellow-600 dark:text-yellow-500" />
                                        </div>
                                        <div class="space-y-1">
                                            <CardTitle class="text-xl font-bold tracking-tight">AI Chapter Generation
                                            </CardTitle>
                                            <CardDescription class="text-base">Choose how you'd like the AI to generate
                                                your academic chapters.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent class="p-8 space-y-8">
                                    <!-- Generation Type Selection -->
                                    <div class="space-y-5">
                                        <h4
                                            class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                            Generation Style</h4>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <label
                                                class="group relative flex cursor-pointer flex-col gap-4 rounded-2xl border-2 p-6 transition-all duration-300 hover:shadow-lg"
                                                :class="generationType === 'progressive'
                                                    ? 'border-primary bg-primary/5 shadow-md ring-1 ring-primary/20'
                                                    : 'border-border/50 bg-card hover:border-primary/50 hover:bg-accent/5'">
                                                <div class="flex items-start justify-between">
                                                    <div
                                                        class="p-2 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                                        <FileText class="h-5 w-5" />
                                                    </div>
                                                    <input type="radio" v-model="generationType" value="progressive"
                                                        class="h-5 w-5 text-primary accent-primary" />
                                                </div>
                                                <div>
                                                    <p
                                                        class="font-bold text-foreground group-hover:text-primary transition-colors">
                                                        Progressive Generation</p>
                                                    <p class="mt-1.5 text-sm text-muted-foreground leading-relaxed">
                                                        Generate one chapter at a time, building context from previous
                                                        work.</p>
                                                </div>
                                            </label>

                                            <label
                                                class="group relative flex cursor-pointer flex-col gap-4 rounded-2xl border-2 p-6 transition-all duration-300 hover:shadow-lg"
                                                :class="generationType === 'bulk'
                                                    ? 'border-primary bg-primary/5 shadow-md ring-1 ring-primary/20'
                                                    : 'border-border/50 bg-card hover:border-primary/50 hover:bg-accent/5'">
                                                <div class="flex items-start justify-between">
                                                    <div
                                                        class="p-2 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                                        <Sparkles class="h-5 w-5" />
                                                    </div>
                                                    <input type="radio" v-model="generationType" value="bulk"
                                                        class="h-5 w-5 text-primary accent-primary" />
                                                </div>
                                                <div>
                                                    <p
                                                        class="font-bold text-foreground group-hover:text-primary transition-colors">
                                                        Complete Project Generation</p>
                                                    <p class="mt-1.5 text-sm text-muted-foreground leading-relaxed">
                                                        Generate entire project with literature, chapters, references,
                                                        and appendices.</p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Generation Actions -->
                                    <div class="pt-4">
                                        <div v-if="generationType === 'progressive'" class="space-y-4">
                                            <Button @click="generateChapter('progressive')"
                                                :disabled="isGenerating || !canGenerateMoreChapters" size="lg"
                                                class="w-full h-14 text-base font-semibold bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 hover:from-blue-700 hover:via-indigo-700 hover:to-purple-700 text-white shadow-lg shadow-indigo-500/25 transition-all hover:scale-[1.01] hover:shadow-indigo-500/40">
                                                <Play v-if="!isGenerating" class="mr-2 h-5 w-5 fill-current" />
                                                <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                                                {{ isGenerating ? 'Generating Chapter...' : canGenerateMoreChapters ?
                                                    `Generate Chapter ${nextChapterNumber}` : 'Chapter Limit Reached' }}
                                            </Button>
                                            <p class="text-sm text-center text-muted-foreground">
                                                <span v-if="canGenerateMoreChapters"
                                                    class="flex items-center justify-center gap-2">
                                                    <Brain class="h-4 w-4 text-indigo-500" />
                                                    AI will generate Chapter <span
                                                        class="font-mono font-bold text-foreground">{{ nextChapterNumber
                                                        }}</span> based on your topic and previous chapters.
                                                </span>
                                                <span v-else
                                                    class="text-amber-600 flex items-center justify-center gap-2">
                                                    <AlertTriangle class="h-4 w-4" />
                                                    You have reached the maximum chapters ({{ maxAllowedChapters }}) for
                                                    this project category.
                                                </span>
                                            </p>
                                        </div>

                                        <div v-if="generationType === 'bulk'" class="space-y-4">
                                            <Button @click="generateChapter('bulk')" :disabled="isGenerating" size="lg"
                                                class="w-full h-14 text-base font-semibold bg-gradient-to-r from-purple-600 via-pink-600 to-rose-600 hover:from-purple-700 hover:via-pink-700 hover:to-rose-700 text-white shadow-lg shadow-pink-500/25 transition-all hover:scale-[1.01] hover:shadow-pink-500/40">
                                                <Zap v-if="!isGenerating" class="mr-2 h-5 w-5 fill-current" />
                                                <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                                                {{ isGenerating ? 'Generating Complete Project...' : 'Generate Complete Project' }}
                                            </Button>
                                            <p
                                                class="text-sm text-center text-muted-foreground flex items-center justify-center gap-2">
                                                <Sparkles class="h-4 w-4 text-pink-500" />
                                                AI will collect literature, generate all chapters, and assemble the
                                                complete project.
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- AI Generation Tips -->
                            <div
                                class="rounded-2xl border border-blue-200/50 bg-gradient-to-br from-blue-50 to-indigo-50/50 p-6 dark:from-blue-950/20 dark:to-indigo-950/20 dark:border-blue-800/30">
                                <div class="flex gap-4">
                                    <div
                                        class="p-2 rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400 h-fit">
                                        <Brain class="h-5 w-5" />
                                    </div>
                                    <div class="space-y-2">
                                        <h4 class="font-semibold text-blue-900 dark:text-blue-100">AI Generation Tips
                                        </h4>
                                        <ul
                                            class="grid gap-2 text-sm text-blue-800/80 dark:text-blue-200/70 sm:grid-cols-2">
                                            <li class="flex items-center gap-2">
                                                <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                Progressive generation builds better context
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                Bulk generation is faster but may need editing
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                All AI content is fully editable
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                Uses your approved topic & requirements
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>

                        <!-- Manual Writing Tab -->
                        <TabsContent value="manual-writing" class="space-y-6">
                            <Card class="border border-border/50 shadow-sm">
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Edit class="h-5 w-5" />
                                        Chapter Management
                                    </CardTitle>
                                    <CardDescription>Create and edit chapters using the full-featured writing editor.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="flex gap-3">
                                        <Button @click="nextChapterNumber ? startChapter(nextChapterNumber) : null"
                                            :disabled="!canGenerateMoreChapters" size="lg" class="flex-1">
                                            <Edit class="mr-2 h-5 w-5" />
                                            {{ canGenerateMoreChapters ? `Start Chapter ${nextChapterNumber}` : 'Chapter Limit Reached' }}
                                        </Button>

                                        <Button v-if="currentMode === 'auto'" @click="generateChapter('single')"
                                            :disabled="isGenerating || !canGenerateMoreChapters" size="lg"
                                            variant="outline" class="flex-1">
                                            <Brain class="mr-2 h-5 w-5" />
                                            AI Draft First
                                        </Button>
                                    </div>
                                    <p class="text-xs text-muted-foreground text-center">
                                        {{ currentMode === 'manual' ? 'Use the full-featured editor to write your chapters from scratch.' : 'Write manually or generate an AI draft to edit and improve.' }}
                                    </p>
                                </CardContent>
                            </Card>
                            <div class="space-y-3">
                                <h4 class="text-sm font-medium">Your Chapters</h4>
                                <div class="grid gap-3">
                                    <!-- Reusing the card style from Overview -->
                                    <Card v-for="i in estimatedChapters" :key="i"
                                        class="group overflow-hidden border border-border/50 shadow-sm transition-all hover:border-primary/20 hover:shadow-md">
                                        <CardContent class="p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <h5 class="font-medium group-hover:text-primary transition-colors">
                                                        {{ getDefaultChapterTitle(i) }}</h5>
                                                    <p class="text-xs text-muted-foreground">Chapter {{ i }}</p>
                                                    <div class="mt-2">
                                                        <template
                                                            v-if="project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '')">
                                                            <Badge
                                                                :class="getChapterStatusBadge(project.chapters.find((c) => c.chapter_number === i)?.status || 'draft')"
                                                                variant="outline">
                                                                {{project.chapters.find((c) => c.chapter_number
                                                                    ===
                                                                    i)?.status.replace('_', ' ')}}
                                                            </Badge>
                                                            <span class="ml-2 text-xs text-muted-foreground">
                                                                {{project.chapters.find((c) => c.chapter_number
                                                                    ===
                                                                    i)?.word_count.toLocaleString()}} words
                                                            </span>
                                                        </template>
                                                        <Badge v-else variant="outline"
                                                            class="bg-muted text-muted-foreground">Not Started
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <Button
                                                        v-if="currentMode === 'auto' && !project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '')"
                                                        @click="generateChapter('progressive', i)" size="sm"
                                                        class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white">
                                                        <Brain class="mr-1 h-4 w-4" />
                                                        Generate
                                                    </Button>

                                                    <Button @click="goToChapter(i)" size="sm"
                                                        :variant="project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '') ? 'outline' : 'default'">
                                                        <Edit class="mr-1 h-4 w-4" />
                                                        {{project.chapters.find((c) => c.chapter_number === i &&
                                                            c.content && c.content.trim() !== '') ? 'Edit' :
                                                            'Start'}}
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </TabsContent>
                    </Tabs>
                </div> <!-- End Main Content Column -->

                <!-- Sidebar Column -->
                <div class="space-y-6 order-1 lg:order-2">
                    <!-- Progress Card -->
                    <div class="rounded-2xl border border-border/50 bg-card p-6 shadow-sm sticky top-6">
                        <h3 class="mb-6 flex items-center gap-2 font-semibold">
                            <Target class="h-5 w-5 text-primary" />
                            Project Progress
                        </h3>

                        <div class="relative mx-auto mb-8 flex h-40 w-40 items-center justify-center">
                            <svg class="h-full w-full -rotate-90 transform" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="8"
                                    class="text-muted/20" />
                                <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="8"
                                    class="text-primary transition-all duration-1000 ease-out" :stroke-dasharray="251.2"
                                    :stroke-dashoffset="251.2 - (251.2 * progressPercentage) / 100"
                                    stroke-linecap="round" />
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-3xl font-bold">{{ Math.round(progressPercentage) }}%</span>
                                <span class="text-xs text-muted-foreground">Completed</span>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between rounded-lg bg-muted/50 p-3">
                                <span class="text-sm text-muted-foreground">Total Words</span>
                                <span class="font-mono font-semibold">{{ totalWordCount.toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-muted/50 p-3">
                                <span class="text-sm text-muted-foreground">Chapters Done</span>
                                <span class="font-mono font-semibold">{{ completedChapters }} / {{ estimatedChapters
                                }}</span>
                            </div>

                        </div>
                    </div>
                </div> <!-- End Sidebar Column -->
            </div> <!-- End Grid -->

            <!-- Mode Change Confirmation Dialog -->
            <Dialog v-model:open="showModeConfirmDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <component :is="pendingNewMode === 'auto' ? Brain : Edit" class="h-5 w-5"
                                :class="pendingNewMode === 'auto' ? 'text-blue-600' : 'text-purple-600'" />
                            Switch Writing Mode
                        </DialogTitle>
                        <DialogDescription class="space-y-4 pt-2 text-left">
                            <Alert :variant="pendingNewMode === 'auto' ? 'default' : 'destructive'" class="border-l-4"
                                :class="pendingNewMode === 'auto' ? 'border-l-blue-500 bg-blue-50 text-blue-900' : 'border-l-purple-500 bg-purple-50 text-purple-900'">
                                <AlertTriangle class="h-4 w-4" />
                                <AlertDescription>
                                    You are about to switch to <strong>{{ pendingNewMode === 'auto' ? 'AI Assisted' :
                                        'Manual'
                                    }}</strong> mode. </AlertDescription>
                            </Alert>

                            <div class="space-y-2 text-sm">
                                <p class="font-medium">What this means:</p>
                                <ul class="space-y-1 text-muted-foreground ml-4 list-disc">
                                    <template v-if="pendingNewMode === 'auto'">
                                        <li>You'll be able to generate chapters with AI</li>
                                        <li>Existing content will remain unchanged</li>
                                    </template>
                                    <template v-else>
                                        <li>You'll write chapters manually</li>
                                        <li>AI generation will be disabled</li>
                                        <li>Existing content will remain unchanged</li>
                                    </template>
                                </ul>
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex-col gap-2 sm:flex-row">
                        <Button @click="showModeConfirmDialog = false" variant="outline" class="w-full sm:w-auto">
                            Cancel
                        </Button>
                        <Button @click="confirmModeChange" :disabled="isTogglingMode"
                            :class="pendingNewMode === 'auto' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-purple-600 hover:bg-purple-700'"
                            class="w-full sm:w-auto text-white">
                            <Clock v-if="isTogglingMode" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isTogglingMode ? 'Switching...' : `Switch to ${pendingNewMode === 'auto' ? 'AI Assisted'
                                : 'Manual'}
                            Mode` }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Bulk Generation Confirmation Dialog -->
            <Dialog v-model:open="showBulkGenerationDialog">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Sparkles class="h-5 w-5 text-purple-600" />
                            Generate Complete Project
                        </DialogTitle>
                        <DialogDescription class="space-y-4 pt-2 text-left">
                            <div
                                class="rounded-xl bg-gradient-to-br from-purple-50 to-pink-50 p-4 border border-purple-100">
                                <div class="flex items-start gap-3">
                                    <Brain class="h-5 w-5 text-purple-600 mt-0.5 flex-shrink-0" />
                                    <div class="space-y-1">
                                        <p class="font-semibold text-purple-900">Comprehensive AI Generation</p>
                                        <p class="text-sm text-purple-800/80">
                                            This will generate a complete project with all {{ maxAllowedChapters
                                            }}
                                            chapters,
                                            literature mining, references, preliminary pages, and appendices.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <p class="text-sm font-medium text-foreground">What will be generated:</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div
                                        class="flex items-center gap-2 rounded-lg border border-border/50 p-2 text-sm text-muted-foreground">
                                        <Check class="h-4 w-4 text-green-500" />
                                        All {{ maxAllowedChapters }} chapters
                                    </div>
                                    <div
                                        class="flex items-center gap-2 rounded-lg border border-border/50 p-2 text-sm text-muted-foreground">
                                        <Check class="h-4 w-4 text-green-500" />
                                        Literature & Citations
                                    </div>
                                    <div
                                        class="flex items-center gap-2 rounded-lg border border-border/50 p-2 text-sm text-muted-foreground">
                                        <Check class="h-4 w-4 text-green-500" />
                                        Preliminary Pages
                                    </div>
                                    <div
                                        class="flex items-center gap-2 rounded-lg border border-border/50 p-2 text-sm text-muted-foreground">
                                        <Check class="h-4 w-4 text-green-500" />
                                        Appendices & References
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-2 text-sm text-muted-foreground bg-muted/50 p-3 rounded-lg border border-border/50">
                                <Clock class="h-4 w-4 text-primary" />
                                <span>Estimated time: <strong>15-20 minutes</strong></span>
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex-col gap-2 sm:flex-row">
                        <Button @click="showBulkGenerationDialog = false" variant="outline" class="w-full sm:w-auto">
                            Cancel
                        </Button>
                        <Button @click="confirmBulkGeneration"
                            class="w-full sm:w-auto bg-gradient-to-r from-purple-600 to-pink-600 text-white hover:from-purple-700 hover:to-pink-700">
                            <Sparkles class="mr-2 h-4 w-4" />
                            Start Generation
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Topic Approval Reset Confirmation Dialog -->
            <Dialog v-model:open="showTopicApprovalDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-destructive">
                            <AlertTriangle class="h-5 w-5" />
                            Return to Topic Approval
                        </DialogTitle>
                        <DialogDescription class="space-y-4 pt-2 text-left">
                            <div class="rounded-xl bg-orange-50 p-4 border border-orange-200">
                                <div class="flex items-start gap-3">
                                    <Target class="h-5 w-5 text-orange-600 mt-0.5 flex-shrink-0" />
                                    <div class="space-y-1">
                                        <p class="font-semibold text-orange-900">Warning: Progress Reset</p>
                                        <p class="text-sm text-orange-800/80">
                                            This will reset your writing progress and return you to the topic
                                            approval
                                            stage. All
                                            chapter content will be preserved, but you'll need to re-approve
                                            your topic.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm font-medium text-foreground">Are you sure you want to continue?
                            </p>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex-col gap-2 sm:flex-row">
                        <Button @click="showTopicApprovalDialog = false" variant="outline" class="w-full sm:w-auto">
                            Cancel
                        </Button>
                        <Button @click="confirmTopicApprovalReset" variant="destructive" class="w-full sm:w-auto">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Reset Progress
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
    </AppLayout>
</template>
