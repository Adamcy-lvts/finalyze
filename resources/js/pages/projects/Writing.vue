<!-- /resources/js/pages/projects/Writing.vue -->
<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { Toggle } from '@/components/ui/toggle';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Activity, ArrowLeft, BookOpen, Brain, Clock, Edit, FileText, Play, Target, Zap } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
    updated_at: string;
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
    console.log('🔄 Project mode prop changed to:', newMode);
    currentMode.value = newMode;
}, { immediate: true });

// Dialog state for mode confirmation
const showModeConfirmDialog = ref(false);
const pendingNewMode = ref<'auto' | 'manual'>('auto');

// Dialog state for bulk generation confirmation
const showBulkGenerationDialog = ref(false);
const showTopicApprovalDialog = ref(false);

// Debug project mode on load
console.log('🔍 PROJECT MODE DEBUG:', {
    projectMode: props.project.mode,
    currentMode: currentMode.value,
    projectData: props.project
});

// Regular ref for Switch state - will be manually synced
const isAutoMode = ref(currentMode.value === 'auto');

// Watch currentMode changes and update switch state
watch(currentMode, (newMode) => {
    console.log('🔄 currentMode changed to:', newMode);
    isAutoMode.value = newMode === 'auto';
}, { immediate: true });

// Handle switch toggle clicks
const handleSwitchToggle = () => {
    if (isTogglingMode.value) {
        console.log('🔄 SWITCH DISABLED - currently toggling');
        return;
    }

    const newMode = currentMode.value === 'auto' ? 'manual' : 'auto';
    console.log('🔄 SWITCH CLICKED:', {
        currentMode: currentMode.value,
        willChangeTo: newMode
    });

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
    return Math.min((totalWordCount.value / props.targetWordCount) * 100, 100);
});

const completedChapters = computed(() => {
    return props.project.chapters.filter((chapter) => chapter.status === 'approved').length;
});

const nextChapterNumber = computed(() => {
    const maxAllowedChapters = props.project.category?.default_chapter_count || props.estimatedChapters;

    // Find the first chapter that is not started or has no content
    const firstUnstartedChapter = props.project.chapters.find(
        (c) => c.status === 'not_started' || c.content === null || c.content === '' || c.content === undefined,
    );

    if (firstUnstartedChapter) {
        // Only return it if it doesn't exceed the category limit
        return firstUnstartedChapter.chapter_number <= maxAllowedChapters ? firstUnstartedChapter.chapter_number : null;
    }

    // If all existing chapters are started, return next number only if within limits
    const maxChapterNumber = Math.max(...props.project.chapters.map((c) => c.chapter_number), 0);
    const nextNumber = maxChapterNumber + 1;

    console.log('nextChapterNumber debug (Writing page):', {
        maxAllowedChapters,
        categoryName: props.project.category?.name,
        maxChapterNumber,
        nextNumber,
        withinLimits: nextNumber <= maxAllowedChapters,
    });

    return nextNumber <= maxAllowedChapters ? nextNumber : null;
});

const canGenerateMoreChapters = computed(() => {
    const maxAllowedChapters = props.project.category?.default_chapter_count || props.estimatedChapters;
    const currentMaxChapter = Math.max(...props.project.chapters.map((c) => c.chapter_number), 0);
    return currentMaxChapter < maxAllowedChapters && nextChapterNumber.value !== null;
});

const maxAllowedChapters = computed(() => {
    return props.project.category?.default_chapter_count || props.estimatedChapters;
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

/**
 * AI CHAPTER GENERATION - AUTO MODE
 * Opens the editor with streaming AI generation
 */
const generateChapter = (type: 'single' | 'progressive' | 'bulk', specificChapter?: number) => {
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
 */
const goToChapter = (chapterNumber: number) => {
    const chapter = props.project.chapters.find(c => c.chapter_number === chapterNumber);
    const hasContent = chapter?.content && chapter.content.trim() !== '';
    
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
 * Always opens in write mode for new content creation
 */
const startChapter = (chapterNumber: number) => {
    router.visit(
        route('chapters.write', {
            project: props.project.slug,
            chapter: chapterNumber,
        }),
    );
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
const goBackToTopicApproval = () => {
    showTopicApprovalDialog.value = true;
};
</script>

<template>
    <AppLayout :title="`Writing: ${project.title}`">
        <div class="mx-auto max-w-6xl space-y-8 p-6">
            <!-- Back Navigation -->
            <div class="flex items-center justify-between">
                <Button @click="goBackToTopicApproval" variant="ghost" size="sm" class="text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Topic Approval
                </Button>
            </div>

            <!-- Header Section -->
            <div class="space-y-4 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                    <BookOpen class="h-8 w-8 text-primary" />
                </div>
                <h1 class="text-3xl font-bold">{{ project.title }}</h1>
                <div class="flex items-center justify-center gap-4 text-sm">
                    <Badge variant="outline" class="capitalize">{{ project.type }}</Badge>
                    <Badge :class="currentMode === 'auto' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'">
                        <Brain v-if="currentMode === 'auto'" class="mr-1 h-3 w-3" />
                        <Edit v-else class="mr-1 h-3 w-3" />
                        {{ currentMode === 'auto' ? 'AI Assisted' : 'Manual Writing' }}
                    </Badge>
                </div>

                <!-- Writing Mode Toggle -->
                <Card class="mx-auto max-w-md border-[0.5px] border-border/50 bg-gradient-to-r from-blue-50 to-purple-50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)] dark:from-blue-900/20 dark:to-purple-900/20">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-r from-blue-500 to-purple-500">
                                    <Brain v-if="currentMode === 'auto'" class="h-5 w-5 text-white" />
                                    <Edit v-else class="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <p class="font-medium text-foreground">Writing Mode</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ currentMode === 'auto' ? 'AI helps generate content' : 'Write everything manually' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <Toggle
                                    v-model:pressed="isAutoMode"
                                    :disabled="isTogglingMode"
                                    @click="handleSwitchToggle"
                                    class="px-6 py-2.5 font-semibold text-sm rounded-xl shadow-lg transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                    :class="currentMode === 'auto'
                                        ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-blue-500/25 hover:shadow-blue-500/40 hover:from-blue-700 hover:to-purple-700 dark:from-blue-500 dark:to-purple-500 dark:shadow-blue-400/25 dark:hover:shadow-blue-400/40'
                                        : 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 shadow-gray-300/50 hover:shadow-gray-400/60 hover:from-gray-200 hover:to-gray-300 dark:from-gray-800 dark:to-gray-700 dark:text-gray-200 dark:shadow-gray-900/50 dark:hover:shadow-gray-900/70'"
                                >
                                    {{ currentMode === 'auto' ? '✨ AI' : '✏️ Manual' }}
                                </Toggle>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Activity & Continue Writing -->
            <Card
                v-if="lastWorkedChapter"
                class="border-[0.5px] border-border/50 bg-gradient-to-r from-amber-50 to-orange-50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)] dark:from-amber-900/20 dark:to-orange-900/20"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Activity class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        Continue Where You Left Off
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="font-semibold text-foreground">{{ lastWorkedChapter.title }}</h4>
                            <p class="mb-2 text-sm text-muted-foreground">Chapter {{ lastWorkedChapter.chapter_number }}</p>
                            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ timeSinceLastUpdate }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <FileText class="h-3 w-3" />
                                    {{ lastWorkedChapter.word_count.toLocaleString() }} words
                                </span>
                                <Badge :class="getChapterStatusBadge(lastWorkedChapter.status)" variant="outline" class="text-xs">
                                    {{ lastWorkedChapter.status.replace('_', ' ') }}
                                </Badge>
                            </div>
                        </div>
                        <Button
                            @click="startChapter(lastWorkedChapter.chapter_number)"
                            class="bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 dark:from-amber-500 dark:to-orange-500 dark:hover:from-amber-600 dark:hover:to-orange-600"
                        >
                            <Play class="mr-2 h-4 w-4" />
                            Resume Writing
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Progress Overview -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Target class="h-5 w-5" />
                        Overall Progress
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Project Progress</span>
                        <span class="font-medium">{{ Math.round(progressPercentage) }}%</span>
                    </div>
                    <Progress :model-value="progressPercentage" class="h-2" />

                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="space-y-1">
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ totalWordCount.toLocaleString() }}</p>
                            <p class="text-xs text-muted-foreground">Total Words</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ completedChapters }}</p>
                            <p class="text-xs text-muted-foreground">Chapters Done</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ estimatedChapters }}</p>
                            <p class="text-xs text-muted-foreground">Total Chapters</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Action Buttons -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Start Writing Button -->
                <Card
                    class="border-[0.5px] border-border/50 bg-gradient-to-r from-green-50 to-blue-50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)] dark:from-green-900/20 dark:to-blue-900/20"
                >
                    <CardContent class="space-y-4 p-6 text-center">
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-r from-green-500 to-blue-500 dark:from-green-400 dark:to-blue-400"
                        >
                            <Edit class="h-6 w-6 text-white" />
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-semibold text-foreground">Start Writing</h3>
                            <p class="text-sm text-muted-foreground">Jump into the editor and start writing your next chapter manually</p>
                        </div>
                        <Button
                            @click="nextChapterNumber ? startChapter(nextChapterNumber) : null"
                            :disabled="!canGenerateMoreChapters"
                            size="lg"
                            class="w-full bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 dark:from-green-500 dark:to-blue-500 dark:hover:from-green-600 dark:hover:to-blue-600"
                        >
                            <Edit class="mr-2 h-5 w-5" />
                            {{ canGenerateMoreChapters ? `Start Writing Chapter ${nextChapterNumber}` : 'All Chapters Created' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Generate Chapter Button (for Auto mode) -->
                <Card
                    v-if="currentMode === 'auto'"
                    class="border-[0.5px] border-border/50 bg-gradient-to-r from-purple-50 to-pink-50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)] dark:from-purple-900/20 dark:to-pink-900/20"
                >
                    <CardContent class="space-y-4 p-6 text-center">
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-r from-purple-500 to-pink-500 dark:from-purple-400 dark:to-pink-400"
                        >
                            <Brain class="h-6 w-6 text-white" />
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-semibold text-foreground">Generate Chapter</h3>
                            <p class="text-sm text-muted-foreground">Let AI create a draft chapter that you can edit and improve</p>
                        </div>
                        <Button
                            @click="generateChapter('progressive')"
                            :disabled="isGenerating || !canGenerateMoreChapters"
                            size="lg"
                            class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 dark:from-purple-500 dark:to-pink-500 dark:hover:from-purple-600 dark:hover:to-pink-600"
                        >
                            <Brain v-if="!isGenerating" class="mr-2 h-5 w-5" />
                            <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                            {{
                                isGenerating
                                    ? 'Generating...'
                                    : canGenerateMoreChapters
                                      ? `Generate Chapter ${nextChapterNumber}`
                                      : 'All Chapters Created'
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Manual Writing Card (for Manual mode) -->
                <Card
                    v-else
                    class="border-[0.5px] border-border/50 bg-gradient-to-r from-purple-50 to-indigo-50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)] dark:from-purple-900/20 dark:to-indigo-900/20"
                >
                    <CardContent class="space-y-4 p-6 text-center">
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 dark:from-purple-400 dark:to-indigo-400"
                        >
                            <BookOpen class="h-6 w-6 text-white" />
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-semibold text-foreground">Manual Mode</h3>
                            <p class="text-sm text-muted-foreground">You're in full control - write everything from scratch using the rich editor</p>
                        </div>
                        <div class="flex gap-2 text-xs">
                            <Badge
                                variant="outline"
                                class="bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                                >Full Control</Badge
                            >
                            <Badge
                                variant="outline"
                                class="bg-indigo-100 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                >No AI</Badge
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Writing Mode Tabs -->
            <Tabs v-model="activeTab" class="space-y-6">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="ai-generation" v-if="currentMode === 'auto'">AI Generation</TabsTrigger>
                    <TabsTrigger value="manual-writing">{{ currentMode === 'auto' ? 'Edit Chapters' : 'Manual Writing' }}</TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="space-y-6">
                    <div class="grid gap-4">
                        <Card
                            v-for="chapter in project.chapters"
                            :key="chapter.id"
                            class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <CardTitle class="text-lg">{{ chapter.title }}</CardTitle>
                                        <CardDescription>Chapter {{ chapter.chapter_number }}</CardDescription>
                                    </div>
                                    <Badge :class="getChapterStatusBadge(chapter.status)" variant="outline">
                                        {{ chapter.status.replace('_', ' ') }}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-muted-foreground">{{ chapter.word_count.toLocaleString() }} words</div>
                                    <div class="flex gap-2">
                                        <!-- Generate Button (for Auto mode and empty chapters) -->
                                        <Button
                                            v-if="currentMode === 'auto' && (!chapter.content || chapter.content.trim() === '')"
                                            @click="generateChapter('progressive', chapter.chapter_number)"
                                            size="sm"
                                            class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white"
                                        >
                                            <Brain class="mr-1 h-4 w-4" />
                                            Generate
                                        </Button>

                                        <!-- Edit Button (for chapters with content) -->
                                        <Button
                                            v-if="chapter.content && chapter.content.trim() !== ''"
                                            @click="editChapter(chapter.chapter_number)"
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Edit class="mr-1 h-4 w-4" />
                                            Edit
                                        </Button>

                                        <!-- Start Button (for empty chapters) -->
                                        <Button
                                            v-else-if="currentMode === 'manual' || !currentMode"
                                            @click="startChapter(chapter.chapter_number)"
                                            size="sm"
                                            variant="default"
                                        >
                                            <Edit class="mr-1 h-4 w-4" />
                                            Start
                                        </Button>

                                        <!-- Not Started Badge (fallback) -->
                                        <Badge v-else variant="outline" class="text-xs text-muted-foreground"> Not Started </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Empty State for No Chapters -->
                        <div v-if="project.chapters.length === 0" class="rounded-lg border-2 border-dashed border-border/50 py-12 text-center">
                            <BookOpen class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <p class="mb-4 text-sm text-muted-foreground">
                                No chapters created yet.
                                {{ currentMode === 'auto' ? 'Use AI generation to get started.' : 'Create your first chapter manually.' }}
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <!-- AI Generation Tab (Auto Mode Only) -->
                <TabsContent v-if="currentMode === 'auto'" value="ai-generation" class="space-y-6">
                    <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Zap class="h-5 w-5" />
                                AI Chapter Generation
                            </CardTitle>
                            <CardDescription> Choose how you'd like the AI to generate your academic chapters. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Generation Type Selection -->
                            <div class="space-y-4">
                                <h4 class="text-sm font-medium">Generation Style</h4>
                                <div class="grid gap-3">
                                    <label class="flex cursor-pointer items-center space-x-3 rounded-lg border p-4 hover:bg-muted/30">
                                        <input type="radio" v-model="generationType" value="progressive" class="h-4 w-4" />
                                        <div class="flex-1">
                                            <p class="font-medium">📝 Progressive Generation</p>
                                            <p class="text-xs text-muted-foreground">
                                                Generate one chapter at a time, building context from previous work
                                            </p>
                                        </div>
                                    </label>
                                    <label class="flex cursor-pointer items-center space-x-3 rounded-lg border p-4 hover:bg-muted/30">
                                        <input type="radio" v-model="generationType" value="bulk" class="h-4 w-4" />
                                        <div class="flex-1">
                                            <p class="font-medium">⚡ Complete Project Generation</p>
                                            <p class="text-xs text-muted-foreground">Generate entire project with literature, chapters, references, and appendices</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Generation Actions -->
                            <div class="space-y-3">
                                <!-- Progressive Generation -->
                                <div v-if="generationType === 'progressive'">
                                    <Button
                                        @click="generateChapter('progressive')"
                                        :disabled="isGenerating || !canGenerateMoreChapters"
                                        size="lg"
                                        class="w-full"
                                    >
                                        <Play v-if="!isGenerating" class="mr-2 h-5 w-5" />
                                        <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                                        {{
                                            isGenerating
                                                ? 'Generating Chapter...'
                                                : canGenerateMoreChapters
                                                  ? `Generate Chapter ${nextChapterNumber}`
                                                  : 'Chapter Limit Reached'
                                        }}
                                    </Button>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        <span v-if="canGenerateMoreChapters">
                                            AI will generate Chapter {{ nextChapterNumber }} based on your topic and previous chapters.
                                        </span>
                                        <span v-else>
                                            You have reached the maximum chapters ({{ maxAllowedChapters }}) for this project category:
                                            {{ project.category?.name }}.
                                        </span>
                                    </p>
                                </div>

                                <!-- Bulk Generation -->
                                <div v-if="generationType === 'bulk'">
                                    <Button @click="generateChapter('bulk')" :disabled="isGenerating" size="lg" class="w-full" variant="default">
                                        <Zap v-if="!isGenerating" class="mr-2 h-5 w-5" />
                                        <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                                        {{ isGenerating ? 'Generating Complete Project...' : `Generate Complete Project` }}
                                    </Button>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        AI will collect literature, generate all chapters with real citations, create preliminary pages, and assemble the complete project.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- AI Generation Tips -->
                    <Alert class="border-blue-200 bg-blue-50 text-blue-800">
                        <Brain class="h-4 w-4" />
                        <AlertDescription>
                            <strong>💡 AI Generation Tips:</strong><br />
                            • Progressive generation builds better context between chapters<br />
                            • Bulk generation is faster but may need more editing<br />
                            • All AI-generated content can be edited and improved<br />
                            • AI uses your approved topic and academic requirements
                        </AlertDescription>
                    </Alert>
                </TabsContent>

                <!-- Manual Writing Tab -->
                <TabsContent value="manual-writing" class="space-y-6">
                    <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Edit class="h-5 w-5" />
                                Chapter Management
                            </CardTitle>
                            <CardDescription> Create and edit chapters using the full-featured writing editor. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Create New Chapter -->
                            <div class="flex gap-3">
                                <Button
                                    @click="nextChapterNumber ? startChapter(nextChapterNumber) : null"
                                    :disabled="!canGenerateMoreChapters"
                                    size="lg"
                                    class="flex-1"
                                >
                                    <Edit class="mr-2 h-5 w-5" />
                                    {{ canGenerateMoreChapters ? `Start Chapter ${nextChapterNumber}` : 'Chapter Limit Reached' }}
                                </Button>

                                <Button
                                    v-if="currentMode === 'auto'"
                                    @click="generateChapter('single')"
                                    :disabled="isGenerating || !canGenerateMoreChapters"
                                    size="lg"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    <Brain class="mr-2 h-5 w-5" />
                                    AI Draft First
                                </Button>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                {{
                                    currentMode === 'manual'
                                        ? 'Use the full-featured editor to write your chapters from scratch.'
                                        : 'Write manually or generate an AI draft to edit and improve.'
                                }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Chapter List for Manual Mode -->
                    <div class="space-y-3">
                        <h4 class="text-sm font-medium">Your Chapters</h4>
                        <div class="grid gap-3">
                            <Card
                                v-for="i in estimatedChapters"
                                :key="i"
                                class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]"
                            >
                                <CardContent class="p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h5 class="font-medium">{{ getDefaultChapterTitle(i) }}</h5>
                                            <p class="text-xs text-muted-foreground">Chapter {{ i }}</p>
                                            <div class="mt-2">
                                                <template
                                                    v-if="
                                                        project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '')
                                                    "
                                                >
                                                    <Badge
                                                        :class="
                                                            getChapterStatusBadge(
                                                                project.chapters.find((c) => c.chapter_number === i)?.status || 'draft',
                                                            )
                                                        "
                                                        variant="outline"
                                                    >
                                                        {{ project.chapters.find((c) => c.chapter_number === i)?.status.replace('_', ' ') }}
                                                    </Badge>
                                                    <span class="ml-2 text-xs text-muted-foreground">
                                                        {{ project.chapters.find((c) => c.chapter_number === i)?.word_count.toLocaleString() }} words
                                                    </span>
                                                </template>
                                                <Badge v-else variant="outline" class="bg-gray-50 text-gray-500"> Not Started </Badge>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <!-- Generate Button (for Auto mode and empty chapters) -->
                                            <Button
                                                v-if="currentMode === 'auto' && !project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '')"
                                                @click="generateChapter('progressive', i)"
                                                size="sm"
                                                class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white"
                                            >
                                                <Brain class="mr-1 h-4 w-4" />
                                                Generate Chapter {{ i }}
                                            </Button>

                                            <!-- Edit/Start Button -->
                                            <Button
                                                @click="goToChapter(i)"
                                                size="sm"
                                                :variant="
                                                    project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '')
                                                        ? 'outline'
                                                        : 'default'
                                                "
                                            >
                                                <Edit class="mr-1 h-4 w-4" />
                                                {{
                                                    project.chapters.find((c) => c.chapter_number === i && c.content && c.content.trim() !== '')
                                                        ? 'Edit'
                                                        : 'Start Writing'
                                                }}
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Mode Change Confirmation Dialog -->
        <Dialog v-model:open="showModeConfirmDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Brain v-if="pendingNewMode === 'auto'" class="h-5 w-5 text-blue-600" />
                        <Edit v-else class="h-5 w-5 text-purple-600" />
                        Switch Writing Mode
                    </DialogTitle>
                    <DialogDescription class="space-y-3 text-left">
                        <p v-if="pendingNewMode === 'auto'" class="text-left">
                            Switch to <strong>AI Assisted</strong> writing mode?
                        </p>
                        <p v-else class="text-left">
                            Switch to <strong>Manual</strong> writing mode?
                        </p>

                        <div class="space-y-1 text-left">
                            <template v-if="pendingNewMode === 'auto'">
                                <p class="text-sm text-left">• You'll be able to generate chapters with AI</p>
                                <p class="text-sm text-left">• Existing content will remain unchanged</p>
                            </template>
                            <template v-else>
                                <p class="text-sm text-left">• You'll write chapters manually</p>
                                <p class="text-sm text-left">• AI generation will be disabled</p>
                                <p class="text-sm text-left">• Existing content will remain unchanged</p>
                            </template>
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button @click="showModeConfirmDialog = false" variant="outline" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button
                        @click="confirmModeChange"
                        :disabled="isTogglingMode"
                        :class="pendingNewMode === 'auto' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-purple-600 hover:bg-purple-700'"
                        class="w-full sm:w-auto"
                    >
                        <Clock v-if="isTogglingMode" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isTogglingMode ? 'Switching...' : `Switch to ${pendingNewMode === 'auto' ? 'AI Assisted' : 'Manual'}` }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Generation Confirmation Dialog -->
        <Dialog v-model:open="showBulkGenerationDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Zap class="h-5 w-5 text-yellow-600" />
                        Generate Complete Project
                    </DialogTitle>
                    <DialogDescription class="space-y-4 text-left">
                        <div class="rounded-lg bg-yellow-50 p-4 border border-yellow-200">
                            <div class="flex items-start gap-3">
                                <Brain class="h-5 w-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                                <div class="space-y-2 text-left">
                                    <p class="font-medium text-yellow-800">Comprehensive AI Generation</p>
                                    <p class="text-sm text-yellow-700">
                                        This will generate a complete project with all {{ maxAllowedChapters }} chapters,
                                        literature mining, references, preliminary pages, and appendices using faculty-specific structure.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 text-left">
                            <p class="font-medium text-foreground">What will be generated:</p>
                            <ul class="text-sm text-muted-foreground space-y-1 ml-4">
                                <li>• All {{ maxAllowedChapters }} chapters with real citations</li>
                                <li>• Literature collection from academic databases</li>
                                <li>• Preliminary pages (title page, abstract, table of contents)</li>
                                <li>• Appendices (bibliography, glossary, data tables)</li>
                                <li>• Defense preparation materials</li>
                            </ul>
                        </div>

                        <div class="flex items-center gap-2 text-sm text-muted-foreground bg-blue-50 p-3 rounded-lg">
                            <Clock class="h-4 w-4 text-blue-600" />
                            <span>This comprehensive process will take approximately <strong>15-20 minutes</strong> to complete.</span>
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button @click="showBulkGenerationDialog = false" variant="outline" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button
                        @click="confirmBulkGeneration"
                        class="w-full sm:w-auto bg-yellow-600 hover:bg-yellow-700"
                    >
                        <Zap class="mr-2 h-4 w-4" />
                        Start Generation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Topic Approval Reset Confirmation Dialog -->
        <Dialog v-model:open="showTopicApprovalDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ArrowLeft class="h-5 w-5 text-orange-600" />
                        Return to Topic Approval
                    </DialogTitle>
                    <DialogDescription class="space-y-3 text-left">
                        <div class="rounded-lg bg-orange-50 p-4 border border-orange-200">
                            <div class="flex items-start gap-3">
                                <Target class="h-5 w-5 text-orange-600 mt-0.5 flex-shrink-0" />
                                <div class="space-y-2 text-left">
                                    <p class="font-medium text-orange-800">Warning: Progress Reset</p>
                                    <p class="text-sm text-orange-700">
                                        This will reset your writing progress and return you to the topic approval stage.
                                        All chapter content will be preserved, but you'll need to re-approve your topic.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">Are you sure you want to continue?</p>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button @click="showTopicApprovalDialog = false" variant="outline" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button
                        @click="confirmTopicApprovalReset"
                        variant="destructive"
                        class="w-full sm:w-auto"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Reset Progress
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
