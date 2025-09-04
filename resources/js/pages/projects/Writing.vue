<!-- resources/js/Pages/Projects/Writing.vue -->
<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Activity, ArrowLeft, BookOpen, Brain, Clock, Edit, FileText, Play, Target, Zap } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
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
    return props.project.chapters.filter((chapter) => chapter.status === 'completed').length;
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
    activeTab.value = props.project.mode === 'auto' ? 'ai-generation' : 'manual-writing';
});

/**
 * AI CHAPTER GENERATION - AUTO MODE
 * Opens the editor with streaming AI generation
 */
const generateChapter = (type: 'single' | 'progressive' | 'bulk') => {
    if (type === 'bulk') {
        const maxChapters = maxAllowedChapters.value;
        // For bulk generation, we'll handle it differently - maybe show a confirmation dialog
        if (
            confirm(
                `Generate all ${maxChapters} chapters at once? This will create complete drafts for all chapters defined in your project category.`,
            )
        ) {
            // Navigate to a bulk generation page or handle bulk generation differently
            toast('Bulk Generation', {
                description: 'Bulk generation coming soon. For now, please generate chapters one by one.',
            });
        }
        return;
    }

    // Check if we can generate more chapters
    const chapterNumber = nextChapterNumber.value;
    if (!chapterNumber) {
        toast('Chapter Limit Reached', {
            description: `You have reached the maximum number of chapters (${maxAllowedChapters.value}) for this project category.`,
        });
        return;
    }

    // For single/progressive generation, navigate to the chapter editor with AI streaming
    router.visit(
        route('chapters.edit', {
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
 * GO BACK TO TOPIC APPROVAL
 * Allows users to modify their topic approval status
 */
const goBackToTopicApproval = async () => {
    if (confirm('This will reset your writing progress and return to topic approval. Continue?')) {
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
    }
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
                    <Badge :class="project.mode === 'auto' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'">
                        <Brain v-if="project.mode === 'auto'" class="mr-1 h-3 w-3" />
                        <Edit v-else class="mr-1 h-3 w-3" />
                        {{ project.mode === 'auto' ? 'AI Assisted' : 'Manual Writing' }}
                    </Badge>
                </div>
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
                            @click="editChapter(lastWorkedChapter.chapter_number)"
                            class="bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 dark:from-amber-500 dark:to-orange-500 dark:hover:from-amber-600 dark:hover:to-orange-600"
                        >
                            <Edit class="mr-2 h-4 w-4" />
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
                            @click="nextChapterNumber ? editChapter(nextChapterNumber) : null"
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
                    v-if="project.mode === 'auto'"
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
                    <TabsTrigger value="ai-generation" v-if="project.mode === 'auto'">AI Generation</TabsTrigger>
                    <TabsTrigger value="manual-writing">{{ project.mode === 'auto' ? 'Edit Chapters' : 'Manual Writing' }}</TabsTrigger>
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
                                    <Button
                                        v-if="chapter.content && chapter.content.trim() !== ''"
                                        @click="editChapter(chapter.chapter_number)"
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Edit class="mr-1 h-4 w-4" />
                                        Edit
                                    </Button>
                                    <Badge v-else variant="outline" class="text-xs text-muted-foreground"> Not Started </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Empty State for No Chapters -->
                        <div v-if="project.chapters.length === 0" class="rounded-lg border-2 border-dashed border-border/50 py-12 text-center">
                            <BookOpen class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <p class="mb-4 text-sm text-muted-foreground">
                                No chapters created yet.
                                {{ project.mode === 'auto' ? 'Use AI generation to get started.' : 'Create your first chapter manually.' }}
                            </p>
                        </div>
                    </div>
                </TabsContent>

                <!-- AI Generation Tab (Auto Mode Only) -->
                <TabsContent v-if="project.mode === 'auto'" value="ai-generation" class="space-y-6">
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
                                            <p class="font-medium">⚡ Bulk Generation</p>
                                            <p class="text-xs text-muted-foreground">Generate all chapters at once for complete first draft</p>
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
                                        {{ isGenerating ? 'Generating All Chapters...' : `Generate All ${maxAllowedChapters} Chapters` }}
                                    </Button>
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        AI will create a complete first draft of all chapters. You can review and edit afterward.
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
                                    @click="nextChapterNumber ? editChapter(nextChapterNumber) : null"
                                    :disabled="!canGenerateMoreChapters"
                                    size="lg"
                                    class="flex-1"
                                >
                                    <Edit class="mr-2 h-5 w-5" />
                                    {{ canGenerateMoreChapters ? `Start Chapter ${nextChapterNumber}` : 'Chapter Limit Reached' }}
                                </Button>

                                <Button
                                    v-if="project.mode === 'auto'"
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
                                    project.mode === 'manual'
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
                                        <Button
                                            @click="editChapter(i)"
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
                                                    : 'Start'
                                            }}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
