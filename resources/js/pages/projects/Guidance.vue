<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft, BookOpen, ChevronDown, ChevronRight, Clock, FileText,
    Lightbulb, Target, Users, CheckCircle, ArrowRight, Brain,
    Zap, AlertCircle, PlayCircle, TrendingUp, Sparkles, RefreshCw, Wand2, X, List
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { toast } from 'vue-sonner';

interface AIGuidance {
    writing_guidance: string;
    key_elements: string[];
    requirements: string[];
    tips: string[];
    methodology_guidance?: string;
    data_guidance?: string;
    analysis_guidance?: string;
    project_specific_notes?: string;
    custom_elements?: string[];
    is_completed?: boolean;
    sections?: ChapterSection[];
}

interface ChapterSection {
    title: string;
    description?: string;
    guidance?: string;
    word_count?: number;
    tips?: string[];
}

interface Chapter {
    number: number;
    title: string;
    word_count: number;
    completion_threshold: number;
    is_required: boolean;
    description?: string;
    ai_guidance?: AIGuidance;
}

interface ProjectStructure {
    preliminary_pages: Record<string, any>;
    chapters: Chapter[];
    appendices: Record<string, any>;
}

interface Timeline {
    research_phase: string;
    writing_phase: string;
    review_phase: string;
    total_duration: string;
}

interface Project {
    id: number;
    title: string;
    faculty: string;
    type: string;
    slug: string;
}

interface Props {
    project: Project;
    structure: ProjectStructure;
    timeline: Timeline;
    facultyName: string;
    hasAnyGuidance: boolean;
    guidanceStatus: 'loaded' | 'missing';
}

const props = defineProps<Props>();

const expandedChapters = ref<Record<number, boolean>>({ 1: true }); // Expand first chapter by default
const expandedSections = ref<Record<string, boolean>>({}); // Track which sections are expanded

// Initialize first section of first chapter as expanded
const initializeSections = () => {
    expandedSections.value['1-0'] = true; // Chapter 1, Section 0 (first section)
};

// Initialize sections when component mounts
initializeSections();
const isTransitioning = ref(false);
const regeneratingChapters = ref<Set<number>>(new Set()); // Track which chapters are being regenerated

// Guidance generation state
const isGeneratingGuidance = ref(false);
const guidanceProgress = ref(0);
const currentGeneratingChapter = ref<number | null>(null);
const generationMessage = ref('Preparing your guidance...');

// Bulk generation state
const isBulkGenerating = ref(false);
const bulkGenerationProgress = ref(0);
const bulkGenerationCurrentChapter = ref<string>('');
const bulkGenerationResults = ref<any[]>([]);
const showBulkProgressModal = ref(false);

// Make chapters reactive so we can update individual chapters with new guidance
const chapters = ref<Chapter[]>([]);

// Watch for changes in props and update reactive chapters
watch(() => props.structure?.chapters, (newChapters) => {
    if (newChapters && Array.isArray(newChapters)) {
        chapters.value = [...newChapters];
    }
}, { immediate: true });

const totalWords = computed(() => {
    return chapters.value.reduce((total, chapter) => total + (chapter.word_count || 0), 0);
});

const toggleChapter = (chapterNumber: number) => {
    expandedChapters.value[chapterNumber] = !expandedChapters.value[chapterNumber];
};

const goBack = () => {
    router.visit(route('projects.show', props.project.slug));
};

const getCsrfToken = () => {
    // Try multiple methods to get CSRF token
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) return metaToken;

    // Try to get from cookie as fallback
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);

    return '';
};

const regenerateAllGuidance = async () => {
    isBulkGenerating.value = true;
    showBulkProgressModal.value = true;
    bulkGenerationProgress.value = 0;
    bulkGenerationCurrentChapter.value = '';
    bulkGenerationResults.value = [];

    try {
        // Use Server-Sent Events for real-time updates
        const eventSource = new EventSource(`/api/projects/${props.project.slug}/guidance/stream-bulk-generation`);

        eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                switch (data.type) {
                    case 'start':
                        bulkGenerationCurrentChapter.value = 'Starting...';
                        break;

                    case 'progress':
                        bulkGenerationProgress.value = data.progress;
                        bulkGenerationCurrentChapter.value = `Chapter ${data.current_chapter}: ${data.chapter_title}`;
                        break;

                    case 'chapter_complete':
                        // Update the specific chapter with new guidance
                        const chapterIndex = chapters.value.findIndex(c => c.number === data.result.chapter_number);
                        if (chapterIndex !== -1) {
                            chapters.value[chapterIndex] = {
                                ...chapters.value[chapterIndex],
                                ai_guidance: data.result.guidance
                            };
                        }

                        // Add to results array
                        const existingIndex = bulkGenerationResults.value.findIndex(r => r.chapter_number === data.result.chapter_number);
                        if (existingIndex >= 0) {
                            bulkGenerationResults.value[existingIndex] = data.result;
                        } else {
                            bulkGenerationResults.value.push(data.result);
                        }
                        break;

                    case 'chapter_error':
                        // Add error result to results array
                        const errorIndex = bulkGenerationResults.value.findIndex(r => r.chapter_number === data.result.chapter_number);
                        if (errorIndex >= 0) {
                            bulkGenerationResults.value[errorIndex] = data.result;
                        } else {
                            bulkGenerationResults.value.push(data.result);
                        }
                        break;

                    case 'complete':
                        bulkGenerationProgress.value = 100;
                        bulkGenerationCurrentChapter.value = 'Complete!';

                        toast('✨ Bulk Generation Complete!', {
                            description: data.message,
                        });

                        // Auto-close modal after a delay
                        setTimeout(() => {
                            showBulkProgressModal.value = false;
                        }, 3000);

                        eventSource.close();
                        isBulkGenerating.value = false;
                        break;

                    case 'error':
                        throw new Error(data.message);
                }
            } catch (parseError) {
                console.error('Failed to parse SSE data:', parseError);
            }
        };

        eventSource.onerror = (error) => {
            console.error('SSE Error:', error);
            eventSource.close();
            isBulkGenerating.value = false;

            toast('❌ Error', {
                description: 'Connection lost. Please try again.',
            });
        };

        // Clean up on component unmount
        const cleanup = () => {
            eventSource.close();
        };

        // Store cleanup function for later use
        (window as any).bulkGenerationCleanup = cleanup;

    } catch (error) {
        console.error('Failed to start bulk generation:', error);
        isBulkGenerating.value = false;

        toast('❌ Error', {
            description: 'Failed to start bulk generation. Please try again.',
        });
    }
};

const closeBulkProgressModal = () => {
    showBulkProgressModal.value = false;
};

const regenerateChapterGuidance = async (chapterNumber: number) => {
    regeneratingChapters.value.add(chapterNumber);

    try {
        const csrfToken = getCsrfToken();

        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page and try again.');
        }

        const response = await fetch(`/api/projects/${props.project.slug}/guidance/regenerate/${chapterNumber}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        });

        // Handle CSRF token mismatch specifically
        if (response.status === 419) {
            toast('🔄 Session Expired', {
                description: 'Please refresh the page and try again.',
            });
            setTimeout(() => {
                window.location.reload();
            }, 2000);
            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
            // Update the specific chapter with new guidance data
            const chapterIndex = chapters.value.findIndex(c => c.number === chapterNumber);
            if (chapterIndex !== -1 && data.guidance) {
                chapters.value[chapterIndex] = {
                    ...chapters.value[chapterIndex],
                    ai_guidance: data.guidance
                };
            }

            toast('✨ Guidance Regenerated!', {
                description: `Fresh AI guidance generated for Chapter ${chapterNumber}.`,
            });
        } else {
            throw new Error(data.message || 'Failed to regenerate guidance');
        }
    } catch (error) {
        console.error('Failed to regenerate guidance:', error);

        let errorMessage = 'Failed to regenerate guidance. Please try again.';
        if (error.message.includes('CSRF')) {
            errorMessage = 'Session expired. Please refresh the page and try again.';
        }

        toast('❌ Error', {
            description: errorMessage,
        });
    } finally {
        regeneratingChapters.value.delete(chapterNumber);
    }
};

const proceedToWriting = async () => {
    isTransitioning.value = true;

    try {
        const csrfToken = getCsrfToken();

        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page and try again.');
        }

        const response = await fetch(`/api/projects/${props.project.slug}/guidance/proceed-to-writing`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        });

        // Handle CSRF token mismatch specifically
        if (response.status === 419) {
            toast('🔄 Session Expired', {
                description: 'Please refresh the page and try again.',
            });
            setTimeout(() => {
                window.location.reload();
            }, 2000);
            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
            toast('🚀 Ready to Write!', {
                description: "Let's start crafting your academic project. Good luck!",
            });

            setTimeout(() => {
                router.visit(route('projects.writing', props.project.slug));
            }, 1000);
        } else {
            throw new Error(data.message || 'Failed to proceed to writing');
        }
    } catch (error) {
        console.error('Failed to proceed to writing:', error);

        let errorMessage = 'Failed to proceed to writing phase. Please try again.';
        if (error.message.includes('CSRF')) {
            errorMessage = 'Session expired. Please refresh the page and try again.';
        }

        toast('❌ Error', {
            description: errorMessage,
        });
        isTransitioning.value = false;
    }
};

// Start guidance generation for projects with no guidance
const startGuidanceGeneration = async () => {
    isGeneratingGuidance.value = true;
    guidanceProgress.value = 0;
    generationMessage.value = 'Preparing your guidance...';

    try {
        // Use the existing bulk generation stream
        const eventSource = new EventSource(`/api/projects/${props.project.slug}/guidance/stream-bulk-generation`);

        eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                switch (data.type) {
                    case 'start':
                        generationMessage.value = 'Starting guidance generation...';
                        break;

                    case 'progress':
                        guidanceProgress.value = data.progress;
                        currentGeneratingChapter.value = data.current_chapter;
                        generationMessage.value = `Generating guidance for Chapter ${data.current_chapter}: ${data.chapter_title}`;
                        break;

                    case 'chapter_complete':
                        // Update the specific chapter with new guidance - will be handled by page refresh
                        break;

                    case 'complete':
                        generationMessage.value = 'Guidance generation completed!';
                        eventSource.close();
                        // Refresh the page to load the new guidance
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                        break;

                    case 'error':
                        generationMessage.value = `Error: ${data.message}`;
                        eventSource.close();
                        isGeneratingGuidance.value = false;
                        break;
                }
            } catch (parseError) {
                console.error('Failed to parse SSE data:', parseError);
            }
        };

        eventSource.onerror = (error) => {
            console.error('SSE connection error:', error);
            eventSource.close();
            isGeneratingGuidance.value = false;
            generationMessage.value = 'Failed to generate guidance. Please try again.';
        };

    } catch (error) {
        console.error('Error starting guidance generation:', error);
        isGeneratingGuidance.value = false;
        generationMessage.value = 'Failed to start guidance generation.';
    }
};

// Auto-start guidance generation if no guidance exists
onMounted(() => {
    if (props.guidanceStatus === 'missing') {
        startGuidanceGeneration();
    }
});

const getChapterIcon = (chapterNumber: number) => {
    const icons = {
        1: Target,
        2: BookOpen,
        3: Users,
        4: TrendingUp,
        5: CheckCircle,
    };
    return icons[chapterNumber] || FileText;
};

const getGuidanceTypeIcon = (type: string) => {
    switch (type) {
        case 'methodology': return Users;
        case 'data': return TrendingUp;
        case 'analysis': return Brain;
        default: return Lightbulb;
    }
};
</script>

<template>
    <AppLayout title="Project Guidance">
        <div class="p-4 md:p-6">
            <div class="max-w-5xl mx-auto space-y-6 md:space-y-8">
                <!-- Header Section -->
                <div class="mb-6 md:mb-8">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="goBack"
                        class="mb-3 md:mb-4 text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft class="mr-2 h-3 w-3 md:h-4 md:w-4" />
                        <span class="text-xs md:text-sm">Back to Project</span>
                    </Button>

                    <div class="space-y-1.5 md:space-y-2">
                        <h1 class="text-2xl md:text-4xl font-bold tracking-tight">
                            Project Guidance
                        </h1>
                        <p class="text-base md:text-xl text-muted-foreground line-clamp-2 md:line-clamp-none">
                            {{ project.title }}
                        </p>
                        <div class="flex flex-wrap items-center gap-3 md:gap-4 text-xs md:text-sm text-muted-foreground">
                            <span class="flex items-center gap-1">
                                <Users class="h-3 w-3 md:h-4 md:w-4" />
                                <span class="hidden sm:inline">{{ facultyName }} Faculty</span>
                                <span class="sm:hidden">{{ facultyName }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <Clock class="h-3 w-3 md:h-4 md:w-4" />
                                {{ timeline.total_duration }}
                            </span>
                            <span class="flex items-center gap-1">
                                <FileText class="h-3 w-3 md:h-4 md:w-4" />
                                <span class="hidden sm:inline">{{ totalWords.toLocaleString() }} words</span>
                                <span class="sm:hidden">{{ Math.round(totalWords / 1000) }}k</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Loading State for Guidance Generation -->
                <div v-if="isGeneratingGuidance" class="space-y-6">
                    <Card class="border border-border shadow-sm bg-card">
                        <CardContent class="p-6 md:p-8 text-center">
                            <div class="flex flex-col items-center space-y-6">
                                <!-- Animated Icon -->
                                <div class="relative">
                                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-muted/30 flex items-center justify-center">
                                        <Brain class="h-8 w-8 md:h-10 md:w-10 text-primary animate-pulse" />
                                    </div>
                                    <div class="absolute inset-0 w-16 h-16 md:w-20 md:h-20 rounded-full border-2 border-muted border-t-primary animate-spin"></div>
                                </div>

                                <!-- Progress Info -->
                                <div class="space-y-4 max-w-lg">
                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-2xl font-bold tracking-tight">Preparing Your Academic Guidance</h3>
                                        <p class="text-muted-foreground text-sm md:text-base">{{ generationMessage }}</p>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="w-full bg-muted rounded-full h-2 overflow-hidden">
                                        <div
                                            class="bg-primary h-full rounded-full transition-all duration-700 ease-out"
                                            :style="{ width: `${guidanceProgress}%` }"
                                        ></div>
                                    </div>

                                    <div class="flex justify-between items-center text-sm text-muted-foreground">
                                        <span class="font-medium">Progress: {{ Math.round(guidanceProgress) }}%</span>
                                        <span v-if="currentGeneratingChapter" class="flex items-center gap-1">
                                            <Target class="h-3 w-3" />
                                            Chapter {{ currentGeneratingChapter }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Status Message -->
                                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 rounded-lg p-4 border border-indigo-200/50 dark:border-indigo-800/30">
                                    <div class="flex items-center gap-2 mb-2">
                                        <Sparkles class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                        <span class="font-semibold text-sm text-indigo-800 dark:text-indigo-200">AI Enhancement in Progress</span>
                                    </div>
                                    <p class="text-xs text-indigo-700 dark:text-indigo-300 leading-relaxed">
                                        Our AI is analyzing your project details and generating personalized, faculty-specific guidance for each chapter. This process ensures you receive the most relevant and detailed academic assistance.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- AI-Powered Timeline -->
                <div v-else-if="hasAnyGuidance" class="space-y-4 md:space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 md:mb-6">
                        <div class="flex items-center gap-2">
                            <Sparkles class="h-4 w-4 md:h-5 md:w-5 text-muted-foreground" />
                            <h2 class="text-lg md:text-2xl font-semibold">AI-Powered Chapter Guide</h2>
                            <Badge variant="secondary" class="text-xs hidden sm:inline-flex">
                                Tailored for {{ facultyName }}
                            </Badge>
                        </div>
                        <Button
                            @click="regenerateAllGuidance"
                            :disabled="isBulkGenerating"
                            variant="outline"
                            size="sm"
                            class="flex items-center gap-2 bg-gradient-to-r from-purple-50 to-blue-50 hover:from-purple-100 hover:to-blue-100 border-purple-200 text-purple-700 w-full sm:w-auto"
                        >
                            <Wand2 :class="['h-3 w-3 md:h-4 md:w-4', isBulkGenerating ? 'animate-spin' : '']" />
                            <span class="text-xs md:text-sm">{{ isBulkGenerating ? 'Generating...' : 'Regenerate All Guidance' }}</span>
                        </Button>
                    </div>

                    <!-- Chapter Timeline -->
                    <div class="relative">
                        <!-- Vertical Timeline Line -->
                        <div class="absolute left-6 md:left-8 top-6 bottom-6 w-0.5 bg-border"></div>

                        <div class="space-y-4 md:space-y-6">
                            <div
                                v-for="(chapter, index) in chapters"
                                :key="chapter.number"
                                class="relative"
                            >
                                <!-- Timeline Node -->
                                <div class="absolute left-4 md:left-6 top-6 w-3 h-3 md:w-4 md:h-4 rounded-full border-2 md:border-4 border-background shadow-lg z-10"
                                     :class="chapter.is_required
                                        ? 'bg-foreground'
                                        : 'bg-muted-foreground'"
                                ></div>

                                <!-- Chapter Card -->
                                <div class="ml-12 md:ml-16">
                                    <Card class="hover:shadow-xl transition-all duration-300 border-2 hover:border-primary/20 bg-gradient-to-br from-background to-muted/10">
                                        <Collapsible :open="expandedChapters[chapter.number]" @update:open="(open) => expandedChapters[chapter.number] = open">
                                            <CollapsibleTrigger class="w-full p-0 text-left">
                                                <CardHeader class="hover:bg-gradient-to-r hover:from-primary/5 hover:to-primary/10 transition-all duration-300 rounded-t-lg p-3 md:p-4">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3 md:gap-5">
                                                            <div class="relative">
                                                                <div class="flex items-center justify-center w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-lg">
                                                                    <component :is="getChapterIcon(chapter.number)" class="h-5 w-5 md:h-6 md:w-6" />
                                                                </div>
                                                                <div v-if="chapter.ai_guidance" class="absolute -top-1 -right-1 w-3 h-3 md:w-4 md:h-4 bg-green-500 rounded-full border-2 border-background flex items-center justify-center">
                                                                    <Sparkles class="h-1.5 w-1.5 md:h-2 md:w-2 text-white" />
                                                                </div>
                                                            </div>
                                                            <div class="space-y-1 md:space-y-2">
                                                                <CardTitle class="text-lg md:text-xl font-bold flex items-center gap-2">
                                                                    Chapter {{ chapter.number }}: {{ chapter.title }}
                                                                    <div v-if="expandedChapters[chapter.number]" class="flex items-center text-xs bg-primary/10 text-primary px-2 py-1 rounded-full hidden md:flex">
                                                                        <span class="animate-pulse">Expanded</span>
                                                                    </div>
                                                                </CardTitle>
                                                                <CardDescription class="flex items-center gap-2 md:gap-4 text-xs md:text-sm">
                                                                    <span class="flex items-center gap-1">
                                                                        <FileText class="h-3 w-3" />
                                                                        <span class="hidden sm:inline">{{ chapter.word_count.toLocaleString() }} words</span>
                                                                        <span class="sm:hidden">{{ Math.round(chapter.word_count / 1000) }}k</span>
                                                                    </span>
                                                                    <Badge :variant="chapter.is_required ? 'default' : 'secondary'" class="text-xs">
                                                                        {{ chapter.is_required ? 'Required' : 'Optional' }}
                                                                    </Badge>
                                                               
                                                                </CardDescription>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-2 md:gap-3">
                                                            <Button
                                                                v-if="chapter.ai_guidance"
                                                                variant="ghost"
                                                                size="sm"
                                                                @click.stop="regenerateChapterGuidance(chapter.number)"
                                                                :disabled="regeneratingChapters.has(chapter.number)"
                                                                class="text-muted-foreground hover:text-primary hover:bg-primary/10 transition-all duration-200 hidden md:inline-flex"
                                                                title="Regenerate AI guidance"
                                                            >
                                                                <RefreshCw
                                                                    :class="[
                                                                        'h-4 w-4',
                                                                        regeneratingChapters.has(chapter.number) ? 'animate-spin text-primary' : ''
                                                                    ]"
                                                                />
                                                            </Button>
                                                            <div class="p-1.5 md:p-2 rounded-lg hover:bg-primary/10 transition-colors">
                                                                <ChevronRight
                                                                    v-if="!expandedChapters[chapter.number]"
                                                                    class="h-4 w-4 md:h-5 md:w-5 text-muted-foreground transition-transform duration-200"
                                                                />
                                                                <ChevronDown
                                                                    v-else
                                                                    class="h-4 w-4 md:h-5 md:w-5 text-primary animate-pulse"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </CardHeader>
                                            </CollapsibleTrigger>

                                            <CollapsibleContent class="overflow-hidden">
                                                <CardContent class="p-3 md:p-4 pt-0 space-y-6 md:space-y-8 animate-in slide-in-from-top-2 duration-300">
                                                    <Separator />

                                                    <!-- AI Guidance Content -->
                                                    <div v-if="chapter.ai_guidance" class="space-y-4 md:space-y-6">
                                                        <!-- Chapter Overview Summary -->
                                                        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-indigo-950/20 dark:to-blue-950/20 rounded-lg p-3 md:p-4 border border-indigo-200 dark:border-indigo-800/30">
                                                            <div class="flex items-center gap-2 mb-1.5 md:mb-2">
                                                                <BookOpen class="h-3.5 w-3.5 md:h-4 md:w-4 text-indigo-600 dark:text-indigo-400" />
                                                                <span class="font-semibold text-xs md:text-sm text-indigo-800 dark:text-indigo-200">Chapter Sections</span>
                                                            </div>
                                                            <p class="text-xs md:text-sm text-indigo-700 dark:text-indigo-300 leading-relaxed">
                                                                This chapter contains {{ chapter.ai_guidance.sections?.length || 0 }} sections with detailed guidance and tips.
                                                                Expand each section below to see specific writing guidance.
                                                            </p>
                                                        </div>


                                                        <!-- Chapter Sections Timeline -->
                                                        <div v-if="chapter.ai_guidance?.sections && Array.isArray(chapter.ai_guidance.sections) && chapter.ai_guidance.sections.length > 0"
                                                             class="space-y-3 md:space-y-4">

                                                            <!-- Section Timeline Header -->
                                                            <div class="flex items-center gap-2 mb-4">
                                                                <div class="w-6 h-0.5 bg-gradient-to-r from-blue-400 dark:from-blue-600 to-transparent"></div>
                                                                <span class="text-sm font-medium text-muted-foreground">Chapter Sections</span>
                                                                <div class="flex-1 h-0.5 bg-gradient-to-r from-blue-200 dark:from-blue-800 to-transparent"></div>
                                                            </div>

                                                            <!-- Individual Sections -->
                                                            <div class="relative pl-6 md:pl-8">
                                                                <!-- Vertical connecting line for sections -->
                                                                <div class="absolute left-1.5 top-3 bottom-0 w-0.5 bg-gradient-to-b from-blue-300 dark:from-blue-700 to-transparent"></div>

                                                                <div class="space-y-4 md:space-y-6">
                                                                    <div v-for="(section, sectionIndex) in chapter.ai_guidance.sections"
                                                                         :key="`${chapter.number}-section-${sectionIndex}`"
                                                                         class="relative">

                                                                        <!-- Section timeline dot -->
                                                                        <div class="absolute -left-5.5 md:-left-7.5 top-6 w-3 h-3 rounded-full border-2 border-background shadow-sm z-10"
                                                                             :class="sectionIndex === 0 ? 'bg-blue-500' : 'bg-muted-foreground/60'"></div>

                                                                        <!-- Section Card -->
                                                                        <Card class="hover:shadow-lg transition-all duration-200 border hover:border-blue-200 dark:hover:border-blue-800/30">
                                                                            <Collapsible v-model:open="expandedSections[`${chapter.number}-${sectionIndex}`]">
                                                                                <CollapsibleTrigger class="w-full p-0 text-left">
                                                                                    <CardHeader class="p-3 md:p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 dark:hover:from-blue-950/10 dark:hover:to-indigo-950/10 transition-all duration-200">
                                                                                        <div class="flex items-center justify-between">
                                                                                            <div class="flex items-center gap-2 md:gap-3">
                                                                                                <div class="flex items-center justify-center w-6 h-6 md:w-8 md:h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-500 text-white text-xs md:text-sm font-bold shadow-sm">
                                                                                                    {{ sectionIndex + 1 }}
                                                                                                </div>
                                                                                                <div>
                                                                                                    <CardTitle class="text-sm md:text-base font-semibold">{{ section.title }}</CardTitle>
                                                                                                    <p class="text-xs md:text-sm text-muted-foreground mt-1">
                                                                                                        Target: {{ section.word_count || 500 }} words
                                                                                                        <span v-if="section.description" class="mx-1 hidden md:inline">•</span>
                                                                                                        <span v-if="section.description" class="line-clamp-1 hidden md:inline">{{ section.description }}</span>
                                                                                                    </p>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="flex items-center gap-1 md:gap-2">
                                                                                                <Badge v-if="sectionIndex === 0" variant="secondary" class="text-xs hidden md:inline-flex">First Section</Badge>
                                                                                                <ChevronDown class="h-3 w-3 md:h-4 md:w-4 text-muted-foreground transition-transform duration-200"
                                                                                                           :class="{ 'rotate-180': expandedSections[`${chapter.number}-${sectionIndex}`] }" />
                                                                                            </div>
                                                                                        </div>
                                                                                    </CardHeader>
                                                                                </CollapsibleTrigger>

                                                                                <CollapsibleContent>
                                                                                    <CardContent class="px-3 md:px-4 pb-3 md:pb-4 pt-0 space-y-3 md:space-y-4">
                                                                                        <!-- Section Description -->
                                                                                        <div v-if="section.description" class="bg-gradient-to-br from-slate-50 to-gray-50 dark:from-slate-950/20 dark:to-gray-950/20 rounded-lg p-3 md:p-4 border border-slate-200 dark:border-slate-800/30">
                                                                                            <div class="flex items-start gap-2 md:gap-3">
                                                                                                <FileText class="h-3.5 w-3.5 md:h-4 md:w-4 text-slate-600 dark:text-slate-400 mt-1 flex-shrink-0" />
                                                                                                <div>
                                                                                                    <p class="font-medium text-xs md:text-sm text-slate-800 dark:text-slate-200 mb-1">Section Overview</p>
                                                                                                    <p class="text-xs md:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ section.description }}</p>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Section Writing Guidance -->
                                                                                        <div v-if="section.guidance" class="bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-950/20 dark:to-yellow-950/20 rounded-lg p-3 md:p-4 border border-amber-200 dark:border-amber-800/30">
                                                                                            <div class="flex items-start gap-2 md:gap-3">
                                                                                                <Lightbulb class="h-3.5 w-3.5 md:h-4 md:w-4 text-amber-600 dark:text-amber-400 mt-1 flex-shrink-0" />
                                                                                                <div>
                                                                                                    <p class="font-medium text-xs md:text-sm text-amber-800 dark:text-amber-200 mb-1">Writing Guidance</p>
                                                                                                    <p class="text-xs md:text-sm text-amber-700 dark:text-amber-300 leading-relaxed">{{ section.guidance }}</p>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Section Tips -->
                                                                                        <div v-if="section.tips && Array.isArray(section.tips) && section.tips.length > 0"
                                                                                             class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-950/20 dark:to-emerald-950/20 rounded-lg p-3 md:p-4 border border-green-200 dark:border-green-800/30">
                                                                                            <div class="flex items-center gap-2 mb-2 md:mb-3">
                                                                                                <Zap class="h-3.5 w-3.5 md:h-4 md:w-4 text-green-600 dark:text-green-400" />
                                                                                                <p class="font-medium text-xs md:text-sm text-green-800 dark:text-green-200">Pro Tips</p>
                                                                                            </div>
                                                                                            <div class="space-y-1.5 md:space-y-2">
                                                                                                <div v-for="(tip, tipIndex) in section.tips"
                                                                                                     :key="tipIndex"
                                                                                                     class="flex items-start gap-2 p-2 bg-green-50/50 dark:bg-green-950/10 rounded-md border border-green-100 dark:border-green-800/20 shadow-sm">
                                                                                                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full mt-1.5 md:mt-2 flex-shrink-0"></div>
                                                                                                    <span class="text-xs md:text-sm text-green-700 dark:text-green-300">{{ tip }}</span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Fallback when section has no content -->
                                                                                        <div v-if="!section.description && !section.guidance && (!section.tips || section.tips.length === 0)"
                                                                                             class="text-center py-6 px-4">
                                                                                            <div class="text-muted-foreground text-sm">
                                                                                                <AlertCircle class="h-8 w-8 mx-auto mb-2 opacity-50" />
                                                                                                <p class="font-medium mb-1">No content available</p>
                                                                                                <p class="text-xs">This section doesn't have detailed guidance yet.</p>
                                                                                            </div>
                                                                                        </div>

                                                                                    </CardContent>
                                                                                </CollapsibleContent>
                                                                            </Collapsible>
                                                                        </Card>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Fallback for old guidance format (has guidance but no sections) -->
                                                        <div v-else-if="chapter.ai_guidance && (!chapter.ai_guidance.sections || !Array.isArray(chapter.ai_guidance.sections) || chapter.ai_guidance.sections.length === 0)"
                                                             class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/20 dark:to-orange-950/20 rounded-lg p-4 md:p-6 border border-amber-200 dark:border-amber-800/30">
                                                            <div class="flex items-start gap-3">
                                                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 flex-shrink-0">
                                                                    <RefreshCw class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                                                </div>
                                                                <div class="flex-1">
                                                                    <h4 class="font-semibold text-sm md:text-base text-amber-800 dark:text-amber-200 mb-2">
                                                                        Enhanced Guidance Available
                                                                    </h4>
                                                                    <p class="text-xs md:text-sm text-amber-700 dark:text-amber-300 leading-relaxed mb-3">
                                                                        This chapter has basic guidance, but our new enhanced system provides detailed section-by-section guidance with specific tips, citation requirements, and faculty-specific advice.
                                                                    </p>
                                                                    <Button
                                                                        @click="regenerateChapterGuidance(chapter.number)"
                                                                        :disabled="regeneratingChapters.has(chapter.number)"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        class="bg-amber-100 hover:bg-amber-200 border-amber-300 text-amber-700"
                                                                    >
                                                                        <RefreshCw :class="['h-3 w-3 mr-2', regeneratingChapters.has(chapter.number) ? 'animate-spin' : '']" />
                                                                        {{ regeneratingChapters.has(chapter.number) ? 'Upgrading...' : 'Upgrade to Enhanced Guidance' }}
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Fallback if no AI guidance at all -->
                                                    <div v-else class="text-center py-12 px-6">
                                                        <div class="relative">
                                                            <div class="absolute inset-0 flex items-center justify-center">
                                                                <div class="w-20 h-20 border-4 border-blue-200 border-t-blue-500 rounded-full animate-spin"></div>
                                                            </div>
                                                            <Brain class="h-16 w-16 mx-auto mb-6 text-blue-500 opacity-60" />
                                                        </div>
                                                        <h4 class="font-semibold text-lg mb-2 text-gray-700">Generating AI Guidance</h4>
                                                        <p class="text-muted-foreground mb-4">Our AI is crafting personalized guidance for this chapter...</p>
                                                        <div class="flex justify-center">
                                                            <Button variant="outline" size="sm" @click="regenerateChapterGuidance(chapter.number)" :disabled="regeneratingChapters.has(chapter.number)">
                                                                <RefreshCw :class="['h-4 w-4 mr-2', regeneratingChapters.has(chapter.number) ? 'animate-spin' : '']" />
                                                                {{ regeneratingChapters.has(chapter.number) ? 'Generating...' : 'Generate Now' }}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Section -->
                <div class="mt-12 text-center">
                    <Card>
                        <CardContent class="p-8">
                            <div class="space-y-4">
                                <h3 class="text-2xl font-bold">Ready to Start Writing?</h3>
                                <p class="text-muted-foreground max-w-2xl mx-auto">
                                    You've reviewed the guidance for your {{ chapters.length }} chapters.
                                    Time to begin your academic journey!
                                </p>
                                <div class="flex gap-4 justify-center">
                                    <Button
                                        @click="proceedToWriting"
                                        :disabled="isTransitioning"
                                        size="lg"
                                        class="font-semibold px-8"
                                    >
                                        <PlayCircle v-if="!isTransitioning" class="mr-2 h-5 w-5" />
                                        <Clock v-else class="mr-2 h-5 w-5 animate-spin" />
                                        {{ isTransitioning ? 'Starting...' : 'Start Writing' }}
                                        <ArrowRight class="ml-2 h-5 w-5" />
                                    </Button>
                                    <Button
                                        @click="goBack"
                                        variant="outline"
                                        size="lg"
                                    >
                                        Review Project
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Bulk Generation Progress Modal -->
        <Dialog :open="showBulkProgressModal" @update:open="closeBulkProgressModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Brain class="h-5 w-5 text-primary" />
                        Generating AI Guidance
                    </DialogTitle>
                    <DialogDescription>
                        Please wait while we generate personalized guidance for all chapters
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-6 py-4">
                    <!-- Progress Bar -->
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Progress</span>
                            <span class="font-medium">{{ Math.round(bulkGenerationProgress) }}%</span>
                        </div>
                        <Progress :value="bulkGenerationProgress" class="h-2" />
                    </div>

                    <!-- Current Status -->
                    <div v-if="isBulkGenerating" class="flex items-center gap-3 p-4 bg-muted/50 rounded-lg">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10">
                            <Sparkles class="h-4 w-4 text-primary animate-pulse" />
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-sm">Generating guidance...</p>
                            <p class="text-xs text-muted-foreground">
                                {{ bulkGenerationCurrentChapter || 'Preparing...' }}
                            </p>
                        </div>
                    </div>

                    <!-- Results Summary -->
                    <div v-if="bulkGenerationResults.length > 0" class="space-y-3">
                        <h4 class="font-medium text-sm">Generation Results:</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="result in bulkGenerationResults"
                                :key="result.chapter_number"
                                class="flex items-center gap-3 p-3 rounded-lg border"
                                :class="result.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                            >
                                <div class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold"
                                     :class="result.success ? 'bg-green-500 text-white' : 'bg-red-500 text-white'">
                                    {{ result.success ? '✓' : '✗' }}
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-sm">Chapter {{ result.chapter_number }}</p>
                                    <p class="text-xs text-muted-foreground">{{ result.chapter_title }}</p>
                                    <p v-if="!result.success" class="text-xs text-red-600 mt-1">{{ result.error }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2 pt-4">
                        <Button
                            v-if="!isBulkGenerating"
                            @click="closeBulkProgressModal"
                            variant="outline"
                            size="sm"
                            class="flex-1"
                        >
                            <X class="h-4 w-4 mr-2" />
                            Close
                        </Button>
                        <Button
                            v-if="bulkGenerationProgress === 100"
                            @click="closeBulkProgressModal"
                            size="sm"
                            class="flex-1"
                        >
                            <CheckCircle class="h-4 w-4 mr-2" />
                            Done
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>