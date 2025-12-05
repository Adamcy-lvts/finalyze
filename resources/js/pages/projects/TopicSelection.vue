<!-- /resources/js/pages/projects/TopicSelection.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Book, BookOpen, CheckCircle, ChevronDown, ChevronUp, FileText, GraduationCap, Layers, Lightbulb, Loader2, RefreshCw, School, Type } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { recordWordUsage } from '@/composables/useWordBalance';

interface Project {
    id: number;
    slug: string;
    title: string | null;
    topic: string | null;
    description: string | null;
    type: string;
    status: string;
    field_of_study: string;
    university: string;
    full_university_name: string;
    course: string;
}

interface Props {
    project: Project;
    savedTopics?: Topic[];
}

const props = defineProps<Props>();

interface Topic {
    id: number;
    title: string;
    description: string;
    difficulty: string;
    timeline: string;
    resource_level: string;
    feasibility_score: number;
    keywords: string[];
    research_type: string;
}

const activeTab = ref('existing');
const isGenerating = ref(false);
const isSelecting = ref(false);
const generatedTopics = ref<Topic[]>([]);
const selectedTopic = ref('');
const selectedDescription = ref('');
const customTopic = ref(''); // This will be the topic title
const customDescription = ref(''); // This will be the description
const customTitle = ref('');
const difficultyFilter = ref('all');
const timelineFilter = ref('all');
const expandedTopics = ref<Set<number>>(new Set());
const usingCachedTopics = ref(false);

const stripHtml = (value: string = ''): string => {
    if (!value) return '';
    return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

onMounted(() => {
    // If user already has a topic, show it
    if (props.project.topic) {
        customTopic.value = props.project.topic;
        customDescription.value = props.project.description || '';
        customTitle.value = props.project.title || '';
        activeTab.value = 'existing';
    }

    // If we have saved topics, load them and switch to generated tab
    if (props.savedTopics && props.savedTopics.length > 0) {
        generatedTopics.value = props.savedTopics;

        // Switch to generated tab if no current topic is set
        if (!props.project.topic) {
            activeTab.value = 'generated';
        }
    }
});

// Generation progress
const generationProgress = ref('');
const currentProgressStep = ref('');

// Progress tracking for better UX
const progressSteps = {
    'connecting': { order: 1, label: 'Connecting to AI service' },
    'analyzing': { order: 2, label: 'Analyzing your project context' },
    'generating': { order: 3, label: 'Generating research topics' },
    'enriching': { order: 4, label: 'Enriching with metadata' },
    'complete': { order: 5, label: 'Complete' }
};

const getStepIndicatorClass = (step: string) => {
    const currentStepOrder = progressSteps[currentProgressStep.value as keyof typeof progressSteps]?.order || 0;
    const stepOrder = progressSteps[step as keyof typeof progressSteps]?.order || 0;

    if (stepOrder < currentStepOrder) {
        return 'h-3 w-3 rounded-full bg-green-500'; // Completed
    } else if (stepOrder === currentStepOrder) {
        return 'h-3 w-3 rounded-full bg-primary animate-pulse'; // Current
    } else {
        return 'h-3 w-3 rounded-full bg-muted'; // Pending
    }
};

const getProgressPercentage = () => {
    const currentStepOrder = progressSteps[currentProgressStep.value as keyof typeof progressSteps]?.order || 0;
    const totalSteps = Object.keys(progressSteps).length;
    return Math.min((currentStepOrder / totalSteps) * 100, 100);
};

const generateTopics = async () => {
    isGenerating.value = true;
    generatedTopics.value = [];
    usingCachedTopics.value = false;

    try {
        // Initialize progress tracking
        currentProgressStep.value = 'connecting';
        generationProgress.value = 'Connecting to AI service...';

        // Start Server-Sent Events connection for real-time progress
        const streamUrl = route('topics.stream', props.project.slug) + '?regenerate=true';

        const eventSource = new EventSource(streamUrl);

        eventSource.onopen = () => {
            currentProgressStep.value = 'connecting';
            generationProgress.value = 'Connected to AI service - analyzing your project...';
        };

        eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                switch (data.type) {
                    case 'start':
                        currentProgressStep.value = 'analyzing';
                        generationProgress.value = data.message || 'Starting topic generation...';
                        break;

                    case 'progress':
                        // Update progress step based on message content
                        if (data.message && data.message.includes('academic context')) {
                            currentProgressStep.value = 'analyzing';
                        } else if (data.message && data.message.includes('generating')) {
                            currentProgressStep.value = 'generating';
                        } else if (data.message && data.message.includes('enriching')) {
                            currentProgressStep.value = 'enriching';
                        }

                        generationProgress.value = data.message || 'Processing...';
                        break;

                    case 'content':
                        currentProgressStep.value = 'generating';
                        if (data.chunk) {
                            // Show dynamic progress as content is being generated
                            const wordCount = data.word_count || 0;
                            generationProgress.value = `Generating topics... (${wordCount} words generated)`;
                        }
                        if (data.topics && data.from_cache) {
                            // Using cached topics for faster response
                            usingCachedTopics.value = true;
                            currentProgressStep.value = 'enriching';
                            generationProgress.value = 'Loading topics from cache for faster response...';
                        }
                        break;

                    case 'complete':
                        if (data.from_cache) {
                            usingCachedTopics.value = true;
                        }
                        currentProgressStep.value = 'complete';
                        if (data.topics && Array.isArray(data.topics)) {
                            generatedTopics.value = data.topics;
                            generationProgress.value = `✓ Successfully generated ${data.topics.length} personalized research topics!`;
                            activeTab.value = 'generated';

                            toast('Success', {
                                description: `${data.topics.length} research topics generated successfully!`,
                            });

                            // Deduct word usage for successful generation (skip if served from cache)
                            recordTopicUsage(
                                data.topics,
                                data.word_count,
                                usingCachedTopics.value
                            );
                        } else {
                            generationProgress.value = data.message || 'Topics generated successfully!';
                        }
                        break;

                    case 'error':
                        throw new Error(data.message || 'Stream error occurred');

                    case 'end':
                        eventSource.close();
                        isGenerating.value = false;
                        break;
                }
            } catch (parseError) {
            }
        };

        eventSource.onerror = (error) => {
            eventSource.close();

            // Check if we already have topics (partial success)
            if (generatedTopics.value.length > 0) {
                generationProgress.value = `✓ Generated ${generatedTopics.value.length} topics (connection ended early)`;
                activeTab.value = 'generated';
                toast('Partial Success', {
                    description: `${generatedTopics.value.length} topics generated before connection ended.`,
                });
            } else {
                // Fallback to regular endpoint if streaming fails
                generateTopicsWithFallback().catch(fallbackError => {
                    generationProgress.value = 'Failed to generate topics. Please try again.';
                    toast('Error', {
                        description: 'Failed to generate topics. Please try again.',
                    });
                });
            }

            isGenerating.value = false;
        };

        // Set a timeout to prevent infinite loading
        setTimeout(() => {
            if (isGenerating.value) {
                eventSource.close();

                if (generatedTopics.value.length > 0) {
                    generationProgress.value = `✓ Generated ${generatedTopics.value.length} topics (timeout reached)`;
                    activeTab.value = 'generated';
                } else {
                    generationProgress.value = 'Generation timed out. Please try again.';
                    toast('Timeout', {
                        description: 'Topic generation took longer than expected. Please try again.',
                    });
                }

                isGenerating.value = false;
            }
        }, 300000); // 5 minutes timeout

    } catch (error: any) {
        generationProgress.value = 'Failed to generate topics. Please try again.';

        toast('Error', {
            description: error.message || 'Failed to generate topics. Please try again.',
        });

        isGenerating.value = false;
    }
};

// Fallback to regular endpoint if streaming fails
const generateTopicsWithFallback = async () => {
    generationProgress.value = 'Retrying with fallback method...';
    usingCachedTopics.value = false;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page and try again.');
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minute timeout

        const response = await fetch(route('topics.generate', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                regenerate: true,
            }),
            signal: controller.signal,
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const responseClone = response.clone();
                const errorData = await responseClone.json();
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                try {
                    const errorText = await response.text();
                    if (response.status === 419) {
                        errorMessage = 'Session expired. Please refresh the page and try again.';
                    } else if (response.status === 504) {
                        errorMessage = 'Request timed out. Please try again in a few moments.';
                    } else if (errorText.includes('<!DOCTYPE')) {
                        errorMessage = 'Server returned an error page instead of data. Please try again.';
                    }
                } catch (textError) {
                    if (response.status === 504) {
                        errorMessage = 'Request timed out. Please try again in a few moments.';
                    }
                }
            }
            throw new Error(errorMessage);
        }

        const data = await response.json();

        if (data.topics && Array.isArray(data.topics)) {
            if (data.from_cache) {
                usingCachedTopics.value = true;
            }
            generatedTopics.value = data.topics;
            generationProgress.value = `✓ Successfully generated ${data.topics.length} research topics!`;
            activeTab.value = 'generated';

            toast('Success', {
                description: `${data.topics.length} research topics generated successfully!`,
            });

            recordTopicUsage(
                data.topics,
                data.word_count,
                usingCachedTopics.value || data.from_cache
            );
        } else {
            throw new Error('Invalid response format: topics array not found');
        }

    } catch (error: any) {
        generationProgress.value = 'Failed to generate topics. Please try again.';

        toast('Error', {
            description: error.message || 'Failed to generate topics. Please try again.',
        });
    }
};

const selectGeneratedTopic = (topic: Topic) => {
    selectedTopic.value = topic.title;
    selectedDescription.value = stripHtml(topic.description);
    customTopic.value = topic.title; // Topic title goes to topic field
    customDescription.value = stripHtml(topic.description); // Use plain text in the form
    customTitle.value = generateTitleFromTopic(topic.title);
    activeTab.value = 'existing';
};

const generateTitleFromTopic = (topic: string): string => {
    return topic.length > 100 ? topic.substring(0, 100) + '...' : topic;
};

const filteredTopics = computed(() => {
    let filtered = generatedTopics.value;

    if (difficultyFilter.value !== 'all') {
        filtered = filtered.filter((topic) => topic.difficulty.toLowerCase().includes(difficultyFilter.value.toLowerCase()));
    }

    if (timelineFilter.value !== 'all') {
        filtered = filtered.filter((topic) => topic.timeline === timelineFilter.value);
    }

    return filtered.sort((a, b) => b.feasibility_score - a.feasibility_score);
});

const getDifficultyVariant = (difficulty: string) => {
    switch (difficulty.toLowerCase()) {
        case 'beginner friendly':
            return 'secondary';
        case 'intermediate':
            return 'default';
        case 'advanced':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const getResourceVariant = (level: string) => {
    switch (level.toLowerCase()) {
        case 'low':
            return 'secondary';
        case 'medium':
            return 'default';
        case 'high':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const toggleDescription = (topicId: number) => {
    if (expandedTopics.value.has(topicId)) {
        expandedTopics.value.delete(topicId);
    } else {
        expandedTopics.value.add(topicId);
    }
};

const truncateDescription = (description: string, maxLength: number = 150) => {
    const plain = stripHtml(description);
    if (plain.length <= maxLength) return plain;
    return plain.substring(0, maxLength).trim() + '...';
};

const isDescriptionTruncated = (description: string, maxLength: number = 150) => {
    return stripHtml(description).length > maxLength;
};

const estimateTopicWordUsage = (topics: Topic[]): number => {
    const combined = topics.map(topic => `${topic.title} ${stripHtml(topic.description)}`).join(' ');
    const words = combined.trim() ? combined.trim().split(/\s+/).filter(Boolean).length : 0;
    // Keep the charge reasonable while avoiding under-counting
    return Math.max(150, Math.min(words, 1200));
};

const recordTopicUsage = (topics: Topic[], wordCount?: number, isFromCache: boolean = false) => {
    if (!topics || topics.length === 0 || isFromCache) return;

    const wordsUsed = wordCount && wordCount > 0
        ? wordCount
        : estimateTopicWordUsage(topics);

    recordWordUsage(
        wordsUsed,
        'Topic generation',
        'project',
        props.project.id
    ).catch(() => {});
};

const submitTopic = async () => {
    if (!customTopic.value.trim() && !customDescription.value.trim()) {
        toast('Error', {
            description: 'Please enter a project topic and description.',
        });
        return;
    }

    isSelecting.value = true;

    try {
        const requestData = {
            topic: customTopic.value.trim() || customTitle.value.trim() || 'Research Topic',
            title: customTitle.value.trim() || generateTitleFromTopic(customTopic.value || customDescription.value),
            description: customDescription.value.trim(),
        };

        // Get CSRF token and validate it exists
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page and try again.');
        }

        const response = await fetch(route('topics.select', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(requestData),
        });

        if (response.ok) {
            const responseData = await response.json();

            toast('Topic Selected!', {
                description: 'Your project topic has been set. You can now submit it for supervisor approval.',
            });

            // Let the middleware handle the redirect to the appropriate next step
            // The middleware will redirect to topic-approval based on the updated project status
            router.visit(route('projects.show', props.project.slug), {
                method: 'get',
                replace: true
            });
        } else {
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                // Response is not JSON, likely HTML error page
                const errorText = await response.text();

                if (response.status === 419) {
                    errorMessage = 'Session expired. Please refresh the page and try again.';
                } else if (errorText.includes('<!DOCTYPE')) {
                    errorMessage = 'Server returned an error page instead of data. Please try again.';
                }
            }
            throw new Error(errorMessage);
        }
    } catch (error) {
        toast('Error', {
            description: 'Failed to select topic. Please try again.',
        });
    } finally {
        isSelecting.value = false;
    }
};

/**
 * GO BACK TO PROJECT WIZARD
 * Allows users to modify their project setup
 */
const goBackToWizard = async () => {
    try {
        // Use Inertia router for better CSRF handling
        router.post(
            route('projects.go-back-to-wizard', props.project.slug),
            {},
            {
                onSuccess: () => {
                    toast('Success', {
                        description: 'Returned to project setup',
                    });
                    // Navigate to project creation page after successful state update
                    router.visit(route('projects.create'));
                },
                onError: () => {
                    toast('Error', {
                        description: 'Failed to go back to setup. Please try again.',
                    });
                },
            },
        );
    } catch (error) {
        toast('Error', {
            description: 'Failed to go back to setup. Please try again.',
        });
    }
};

// Cleanup on unmount - no longer needed for fetch streaming
</script>

<template>
    <AppLayout title="Select Project Topic">
        <div class="min-h-screen bg-gradient-to-b from-background via-background/95 to-muted/20">
            <div class="mx-auto max-w-5xl space-y-10 p-6 pb-20 lg:p-10">
                <!-- Back Navigation -->
                <div class="flex items-center justify-between">
                    <Button @click="goBackToWizard" variant="ghost" size="sm"
                        class="group text-muted-foreground hover:text-foreground transition-colors">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-muted/50 group-hover:bg-primary/10 transition-colors mr-2">
                            <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                        </div>
                        Back to Project Setup
                    </Button>
                </div>

                <!-- Header -->
                <div class="space-y-4 text-center animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div
                        class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 shadow-lg shadow-primary/10 ring-1 ring-white/20">
                        <Lightbulb class="h-10 w-10 text-primary" />
                    </div>
                    <div class="space-y-2">
                        <h1
                            class="text-4xl font-bold tracking-tight sm:text-5xl bg-gradient-to-br from-foreground to-foreground/70 bg-clip-text text-transparent">
                            Choose Your Project Topic
                        </h1>
                        <p class="mx-auto max-w-2xl text-lg text-muted-foreground leading-relaxed">
                            Select or generate a research topic for your <span class="font-medium text-foreground">{{
                                project.type }}</span>
                            <span class="font-medium text-foreground">{{ project.field_of_study }}</span> project.
                        </p>
                    </div>
                </div>

                <!-- Project Context -->
                <div class="animate-in fade-in slide-in-from-bottom-6 duration-700 delay-100">
                    <Card class="overflow-hidden border-border/40 bg-card/50 backdrop-blur-sm shadow-sm">
                        <div
                            class="grid grid-cols-1 divide-y divide-border/40 md:grid-cols-4 md:divide-x md:divide-y-0">
                            <div class="p-6 flex flex-col gap-3 group hover:bg-muted/20 transition-colors">
                                <div
                                    class="flex items-center gap-2 text-muted-foreground group-hover:text-primary transition-colors">
                                    <BookOpen class="h-4 w-4" />
                                    <span class="text-xs font-medium uppercase tracking-wider">Field of Study</span>
                                </div>
                                <div class="font-semibold text-lg leading-tight">
                                    {{ project.field_of_study }}
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-3 group hover:bg-muted/20 transition-colors">
                                <div
                                    class="flex items-center gap-2 text-muted-foreground group-hover:text-primary transition-colors">
                                    <GraduationCap class="h-4 w-4" />
                                    <span class="text-xs font-medium uppercase tracking-wider">Academic Level</span>
                                </div>
                                <div class="font-semibold text-lg leading-tight capitalize">
                                    {{ project.type }}
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-3 group hover:bg-muted/20 transition-colors">
                                <div
                                    class="flex items-center gap-2 text-muted-foreground group-hover:text-primary transition-colors">
                                    <School class="h-4 w-4" />
                                    <span class="text-xs font-medium uppercase tracking-wider">University</span>
                                </div>
                                <div class="font-semibold text-lg leading-tight truncate"
                                    :title="project.full_university_name || project.university">
                                    {{ project.full_university_name || project.university }}
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-3 group hover:bg-muted/20 transition-colors">
                                <div
                                    class="flex items-center gap-2 text-muted-foreground group-hover:text-primary transition-colors">
                                    <Book class="h-4 w-4" />
                                    <span class="text-xs font-medium uppercase tracking-wider">Course</span>
                                </div>
                                <div class="font-semibold text-lg leading-tight">
                                    {{ project.course }}
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Topic Selection Tabs -->
                <div class="animate-in fade-in slide-in-from-bottom-8 duration-700 delay-200">
                    <Tabs v-model="activeTab" class="space-y-8">
                        <div class="flex justify-center">
                            <TabsList class="grid w-full max-w-md grid-cols-2 p-1 bg-muted/50 backdrop-blur-sm">
                                <TabsTrigger value="existing"
                                    class="data-[state=active]:bg-background data-[state=active]:shadow-sm transition-all duration-300">
                                    Enter Topic
                                </TabsTrigger>
                                <TabsTrigger value="generated"
                                    class="data-[state=active]:bg-background data-[state=active]:shadow-sm transition-all duration-300">
                                    AI Generated Topics
                                </TabsTrigger>
                            </TabsList>
                        </div>

                        <!-- Enter Existing Topic -->
                        <TabsContent value="existing" class="focus-visible:outline-none">
                            <Card class="border-border/40 shadow-lg shadow-primary/5 overflow-hidden">
                                <div
                                    class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary/50 to-transparent opacity-20">
                                </div>
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-xl">Enter Your Project Topic</CardTitle>
                                    <CardDescription>
                                        If you already have a project topic (approved by supervisor or from your own
                                        research), enter it below.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-6 pt-6">
                                    <div class="space-y-5">
                                        <div class="space-y-2">
                                            <Label for="custom-title"
                                                class="text-sm font-medium text-foreground/80 flex items-center gap-2">
                                                <Type class="h-4 w-4 text-primary" />
                                                Project Title
                                            </Label>
                                            <Input id="custom-title" v-model="customTitle"
                                                placeholder="A concise title for your project..."
                                                class="h-12 bg-muted/30 border-border/50 focus:bg-background focus:ring-2 focus:ring-primary/20 transition-all" />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="custom-topic"
                                                class="text-sm font-medium text-foreground/80 flex items-center gap-2">
                                                <Lightbulb class="h-4 w-4 text-primary" />
                                                Research Topic
                                            </Label>
                                            <Textarea id="custom-topic" v-model="customTopic"
                                                placeholder="Enter your research topic or question..." rows="3"
                                                class="resize-none bg-muted/30 border-border/50 focus:bg-background focus:ring-2 focus:ring-primary/20 transition-all min-h-[100px]" />
                                            <p class="text-xs text-muted-foreground pl-1">The main research topic or
                                                question you want to investigate.</p>
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="custom-description"
                                                class="text-sm font-medium text-foreground/80 flex items-center gap-2">
                                                <FileText class="h-4 w-4 text-primary" />
                                                Project Description
                                            </Label>
                                            <Textarea id="custom-description" v-model="customDescription"
                                                placeholder="Describe your research topic, problem statement, and what you plan to investigate..."
                                                rows="6"
                                                class="resize-none bg-muted/30 border-border/50 focus:bg-background focus:ring-2 focus:ring-primary/20 transition-all min-h-[160px]" />
                                            <p class="text-xs text-muted-foreground pl-1">
                                                Provide a detailed description of your research focus, objectives, and
                                                scope.
                                            </p>
                                        </div>

                                        <div class="pt-4">
                                            <Button @click="submitTopic"
                                                :disabled="(!customTopic.trim() && !customDescription.trim()) || isSelecting"
                                                class="w-full h-12 text-base font-medium shadow-lg shadow-primary/20 hover:shadow-primary/30 hover:scale-[1.01] transition-all">
                                                <Loader2 v-if="isSelecting" class="mr-2 h-5 w-5 animate-spin" />
                                                <span v-else class="flex items-center">
                                                    Continue with This Topic
                                                    <ArrowRight class="ml-2 h-5 w-5" />
                                                </span>
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <!-- AI Generated Topics -->
                        <TabsContent value="generated" class="focus-visible:outline-none">
                            <Card
                                class="border-border/40 shadow-lg shadow-primary/5 overflow-hidden min-h-[500px] flex flex-col">
                                <CardHeader class="border-b border-border/40 bg-muted/10 pb-6">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="space-y-1">
                                            <CardTitle class="flex items-center gap-2 text-xl">
                                                <div class="p-1.5 rounded-md bg-primary/10 text-primary">
                                                    <Lightbulb class="h-5 w-5" />
                                                </div>
                                                AI Generated Suggestions
                                            </CardTitle>
                                            <CardDescription>
                                                Tailored research topics based on your academic profile.
                                            </CardDescription>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <Button @click="generateTopics" :disabled="isGenerating"
                                                :variant="generatedTopics.length > 0 ? 'outline' : 'default'"
                                                class="h-10 transition-all"
                                                :class="generatedTopics.length === 0 ? 'shadow-lg shadow-primary/20 hover:shadow-primary/30' : ''">
                                                <Loader2 v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                                                <RefreshCw v-else-if="generatedTopics.length > 0"
                                                    class="mr-2 h-4 w-4" />
                                                <Lightbulb v-else class="mr-2 h-4 w-4" />
                                                {{ isGenerating ? 'Generating...' : generatedTopics.length > 0 ?
                                                    'Regenerate' : 'Generate Topics' }}
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Filters -->
                                    <div v-if="generatedTopics.length > 0"
                                        class="mt-6 flex flex-wrap items-center gap-4 animate-in fade-in slide-in-from-top-2 bg-muted/30 p-3 rounded-xl border border-border/40">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-xs font-medium px-2 text-muted-foreground uppercase tracking-wider">Filter
                                                By:</span>
                                        </div>
                                        <div class="relative group">
                                            <select v-model="difficultyFilter"
                                                class="h-9 pl-3 pr-8 rounded-lg border border-border/50 bg-background text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all appearance-none cursor-pointer min-w-[140px]">
                                                <option value="all">All Difficulties</option>
                                                <option value="beginner">Beginner Friendly</option>
                                                <option value="intermediate">Intermediate</option>
                                                <option value="advanced">Advanced</option>
                                            </select>
                                            <ChevronDown
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none group-hover:text-primary transition-colors" />
                                        </div>
                                        <div class="relative group">
                                            <select v-model="timelineFilter"
                                                class="h-9 pl-3 pr-8 rounded-lg border border-border/50 bg-background text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all appearance-none cursor-pointer min-w-[140px]">
                                                <option value="all">Any Duration</option>
                                                <option value="6-9 months">6-9 months</option>
                                                <option value="9-12 months">9-12 months</option>
                                                <option value="12+ months">12+ months</option>
                                            </select>
                                            <ChevronDown
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none group-hover:text-primary transition-colors" />
                                        </div>
                                        <div class="ml-auto flex items-center gap-2">
                                            <span
                                                class="text-xs text-muted-foreground hidden sm:inline-block">Showing</span>
                                            <Badge variant="secondary"
                                                class="h-7 px-3 text-xs font-medium bg-primary/10 text-primary border-primary/20">
                                                {{ filteredTopics.length }} Topics
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent class="p-0 flex-1 bg-muted/5">
                                    <!-- Loading State -->
                                    <div v-if="isGenerating"
                                        class="flex flex-col items-center justify-center h-[400px] p-8 text-center space-y-8 animate-in fade-in duration-500">
                                        <div class="relative">
                                            <div
                                                class="absolute inset-0 rounded-full bg-primary/20 animate-ping opacity-75">
                                            </div>
                                            <div
                                                class="relative flex items-center justify-center h-20 w-20 rounded-full bg-background border-2 border-primary/20 shadow-xl">
                                                <Loader2 class="h-10 w-10 text-primary animate-spin" />
                                            </div>
                                        </div>

                                        <div class="space-y-4 max-w-md w-full">
                                            <h3
                                                class="text-xl font-semibold bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-transparent">
                                                Crafting Your Topics
                                            </h3>

                                            <div class="space-y-2">
                                                <div
                                                    class="flex justify-between text-xs font-medium text-muted-foreground px-1">
                                                    <span>{{ generationProgress || 'Initializing...' }}</span>
                                                    <span>{{ Math.round(getProgressPercentage()) }}%</span>
                                                </div>
                                                <div class="h-2 w-full bg-muted/50 rounded-full overflow-hidden">
                                                    <div class="h-full bg-primary transition-all duration-500 ease-out relative overflow-hidden"
                                                        :style="`width: ${getProgressPercentage()}%`">
                                                        <div
                                                            class="absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite]">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-4 gap-2 pt-4">
                                                <div v-for="(step, key) in progressSteps" :key="key"
                                                    class="flex flex-col items-center gap-2"
                                                    :class="key === 'complete' ? 'hidden' : ''">
                                                    <div class="h-2 w-2 rounded-full transition-colors duration-300"
                                                        :class="getStepIndicatorClass(key as string)"></div>
                                                    <span
                                                        class="text-[10px] uppercase tracking-wider font-medium text-muted-foreground"
                                                        :class="currentProgressStep === key ? 'text-primary' : ''">
                                                        {{ key }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-else-if="generatedTopics.length === 0"
                                        class="flex flex-col items-center justify-center h-[400px] p-8 text-center space-y-6 animate-in fade-in zoom-in-95 duration-500">
                                        <div
                                            class="h-24 w-24 rounded-3xl bg-muted/30 flex items-center justify-center mb-2">
                                            <Lightbulb class="h-12 w-12 text-muted-foreground/50" />
                                        </div>
                                        <div class="max-w-sm space-y-2">
                                            <h3 class="text-lg font-semibold">No Topics Generated Yet</h3>
                                            <p class="text-muted-foreground text-sm">
                                                Click the "Generate Topics" button to let our AI analyze your profile
                                                and suggest personalized research topics.
                                            </p>
                                        </div>
                                        <Button @click="generateTopics" size="lg"
                                            class="shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all">
                                            <Lightbulb class="mr-2 h-5 w-5" />
                                            Generate Topics
                                        </Button>
                                    </div>

                                    <!-- Topics List -->
                                    <div v-else class="p-6 grid gap-6 md:grid-cols-2">
                                        <div v-for="topic in filteredTopics" :key="topic.id"
                                            class="group relative flex flex-col rounded-xl border border-border/50 bg-card transition-all duration-300 hover:shadow-xl hover:shadow-primary/5 hover:border-primary/30 hover:-translate-y-1 overflow-hidden"
                                            :class="selectedTopic === topic.title ? 'ring-2 ring-primary border-primary bg-primary/5' : ''">
                                            <!-- Selection Overlay (Active State) -->
                                            <div v-if="selectedTopic === topic.title"
                                                class="absolute top-0 right-0 p-0 z-10">
                                                <div
                                                    class="bg-primary text-primary-foreground rounded-bl-xl px-3 py-1.5 shadow-sm flex items-center gap-1.5">
                                                    <CheckCircle class="h-3.5 w-3.5" />
                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wider">Selected</span>
                                                </div>
                                            </div>

                                            <div class="p-6 flex flex-col gap-4 flex-1">
                                                <!-- Header -->
                                                <div class="space-y-3">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <h3
                                                            class="text-lg font-bold leading-tight text-foreground group-hover:text-primary transition-colors line-clamp-2">
                                                            {{ topic.title }}
                                                        </h3>
                                                    </div>

                                                    <div class="flex flex-wrap gap-2">
                                                        <Badge :variant="getDifficultyVariant(topic.difficulty)"
                                                            class="text-[10px] uppercase tracking-wider font-semibold py-0.5">
                                                            {{ topic.difficulty }}
                                                        </Badge>
                                                        <Badge variant="outline"
                                                            class="text-[10px] uppercase tracking-wider font-medium border-border text-muted-foreground">
                                                            {{ topic.timeline }}
                                                        </Badge>
                                                        <Badge variant="secondary"
                                                            class="text-[10px] uppercase tracking-wider font-medium bg-muted/50">
                                                            {{ topic.research_type }}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <!-- Description -->
                                                <div class="relative flex-1">
                                                    <div class="text-sm text-muted-foreground leading-relaxed prose prose-sm prose-invert max-w-none"
                                                        :class="!expandedTopics.has(topic.id) ? 'line-clamp-3' : ''"
                                                        v-html="topic.description">
                                                    </div>
                                                    <button v-if="isDescriptionTruncated(topic.description)"
                                                        @click="toggleDescription(topic.id)"
                                                        class="mt-2 text-xs font-medium text-primary hover:text-primary/80 flex items-center gap-1 transition-colors">
                                                        {{ expandedTopics.has(topic.id) ? 'Show less' : 'Read more' }}
                                                        <ChevronDown class="h-3 w-3 transition-transform duration-300"
                                                            :class="expandedTopics.has(topic.id) ? 'rotate-180' : ''" />
                                                    </button>
                                                </div>

                                                <!-- Keywords -->
                                                <div class="flex flex-wrap gap-1.5 pt-2">
                                                    <span v-for="keyword in topic.keywords.slice(0, 3)" :key="keyword"
                                                        class="text-[10px] font-medium text-muted-foreground bg-muted/30 px-2 py-1 rounded-md border border-border/30">
                                                        #{{ keyword }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Footer / Action -->
                                            <div
                                                class="p-4 bg-muted/10 border-t border-border/40 flex items-center justify-between gap-4 mt-auto">
                                                <!-- Feasibility Score -->
                                                <div class="flex items-center gap-3">
                                                    <div class="relative h-10 w-10 flex items-center justify-center">
                                                        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                                                            <!-- Background Circle -->
                                                            <path class="text-muted/30"
                                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                fill="none" stroke="currentColor" stroke-width="3" />
                                                            <!-- Progress Circle -->
                                                            <path
                                                                :class="topic.feasibility_score >= 80 ? 'text-green-500' : topic.feasibility_score >= 60 ? 'text-yellow-500' : 'text-red-500'"
                                                                :stroke-dasharray="`${topic.feasibility_score}, 100`"
                                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                fill="none" stroke="currentColor" stroke-width="3"
                                                                stroke-linecap="round" />
                                                        </svg>
                                                        <span class="absolute text-[10px] font-bold">{{
                                                            topic.feasibility_score }}%</span>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Feasibility</span>
                                                        <span class="text-xs font-medium"
                                                            :class="topic.feasibility_score >= 80 ? 'text-green-600' : topic.feasibility_score >= 60 ? 'text-yellow-600' : 'text-red-600'">
                                                            {{ topic.feasibility_score >= 80 ? 'High' :
                                                                topic.feasibility_score >= 60 ? 'Medium' : 'Low' }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <Button size="sm" @click="selectGeneratedTopic(topic)"
                                                    :variant="selectedTopic === topic.title ? 'secondary' : 'default'"
                                                    class="shadow-sm transition-all duration-300"
                                                    :class="selectedTopic === topic.title ? 'bg-green-100 text-green-700 hover:bg-green-200 border-green-200' : 'hover:shadow-md hover:scale-105'">
                                                    {{ selectedTopic === topic.title ? 'Selected' : 'Select Topic' }}
                                                    <CheckCircle v-if="selectedTopic === topic.title"
                                                        class="ml-2 h-3.5 w-3.5" />
                                                    <ArrowRight v-else class="ml-2 h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
