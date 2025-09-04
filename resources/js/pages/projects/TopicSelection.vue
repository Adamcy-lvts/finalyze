<!-- resources/js/Pages/Projects/TopicSelection.vue -->
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
import { ArrowLeft, ArrowRight, BookOpen, CheckCircle, ChevronDown, ChevronUp, Lightbulb, Loader2, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

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
    course: string;
}

interface Props {
    project: Project;
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

onMounted(() => {
    // If user already has a topic, show it
    if (props.project.topic) {
        customTopic.value = props.project.topic;
        customDescription.value = props.project.description || '';
        customTitle.value = props.project.title || '';
        activeTab.value = 'existing';
    }
});

// Streaming-related variables
const eventSource = ref<EventSource | null>(null);
const generationProgress = ref('');
const streamingContent = ref('');

const generateTopics = async () => {
    if (isGenerating.value) return;

    isGenerating.value = true;
    generationProgress.value = 'Connecting to AI service...';
    streamingContent.value = '';
    generatedTopics.value = [];

    try {
        // Build URL with query parameters for streaming
        const url = new URL(route('topics.stream', props.project.slug));
        url.searchParams.append('project_id', props.project.id.toString());
        url.searchParams.append('regenerate', (generatedTopics.value.length > 0).toString());

        // Create EventSource for Server-Sent Events
        eventSource.value = new EventSource(url.toString(), {
            withCredentials: true,
        });

        // Handle incoming messages
        eventSource.value.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                switch (data.type) {
                    case 'start':
                        generationProgress.value = data.message;
                        break;

                    case 'progress':
                        generationProgress.value = data.message;
                        if (data.context) {
                            const context = data.context;
                            generationProgress.value += ` (${context.academic_level} in ${context.field_of_study})`;
                        }
                        break;

                    case 'content':
                        if (data.from_cache) {
                            // Handle cached topics
                            generatedTopics.value = data.topics.map((topic: string, index: number) => ({
                                id: index + 1,
                                title: topic,
                                description: `Generated topic for ${props.project.field_of_study}`,
                                difficulty: 'Medium',
                                timeline: '12-18 months',
                                resource_level: 'Moderate',
                                feasibility_score: 0.8,
                                keywords: topic.toLowerCase().split(' ').slice(0, 3),
                                research_type: 'Applied Research',
                            }));
                            generationProgress.value = data.message;
                        } else {
                            // Handle streaming content
                            if (data.chunk) {
                                streamingContent.value += data.chunk;
                            }
                            if (data.content) {
                                streamingContent.value = data.content;
                            }
                            generationProgress.value = `Generating... (${data.word_count || 0} words)`;
                        }
                        break;

                    case 'complete':
                        isGenerating.value = false;
                        if (data.topics) {
                            generatedTopics.value = data.topics;
                        }
                        generationProgress.value = `✓ ${data.message} (${data.total_topics} topics generated)`;
                        activeTab.value = 'generated';

                        toast('Success', {
                            description: `${data.total_topics} topics generated successfully!`,
                        });
                        break;

                    case 'error':
                        isGenerating.value = false;
                        generationProgress.value = 'Generation failed';

                        toast('Error', {
                            description: data.message || 'Failed to generate topics',
                        });
                        break;

                    case 'end':
                        eventSource.value?.close();
                        eventSource.value = null;
                        break;
                }
            } catch (error) {
                console.error('Failed to parse SSE data:', error);
            }
        };

        // Handle connection errors
        eventSource.value.onerror = (error) => {
            console.error('EventSource error:', error);
            isGenerating.value = false;
            generationProgress.value = 'Connection lost';

            // Close and cleanup
            eventSource.value?.close();
            eventSource.value = null;

            toast('Connection Error', {
                description: 'Lost connection to AI service. Please try again.',
            });
        };
    } catch (error) {
        isGenerating.value = false;
        toast('Error', {
            description: 'Failed to start topic generation. Please try again.',
        });
    }
};

const selectGeneratedTopic = (topic: Topic) => {
    selectedTopic.value = topic.title;
    selectedDescription.value = topic.description;
    customTopic.value = topic.title; // Topic title goes to topic field
    customDescription.value = topic.description; // Description goes to description field
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
    if (description.length <= maxLength) return description;
    return description.substring(0, maxLength).trim() + '...';
};

const isDescriptionTruncated = (description: string, maxLength: number = 150) => {
    return description.length > maxLength;
};

const submitTopic = async () => {
    console.log('🚀 TOPIC SUBMISSION - Starting', {
        customTopic: customTopic.value?.substring(0, 50) + '...',
        customDescription: customDescription.value?.substring(0, 50) + '...',
        customTitle: customTitle.value,
        project_id: props.project.id,
        project_slug: props.project.slug,
    });

    if (!customTopic.value.trim() && !customDescription.value.trim()) {
        console.log('❌ TOPIC SUBMISSION - Validation failed: No topic or description');
        toast('Error', {
            description: 'Please enter a project topic and description.',
        });
        return;
    }

    isSelecting.value = true;

    try {
        const requestData = {
            project_id: props.project.id,
            topic: customTopic.value.trim() || customTitle.value.trim() || 'Research Topic',
            title: customTitle.value.trim() || generateTitleFromTopic(customTopic.value || customDescription.value),
            description: customDescription.value.trim(),
        };

        console.log('📤 TOPIC SUBMISSION - Request data:', requestData);
        console.log('📍 TOPIC SUBMISSION - Request URL:', route('topics.select', props.project.slug));

        const response = await fetch(route('topics.select', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(requestData),
        });

        console.log('📥 TOPIC SUBMISSION - Response status:', response.status);
        console.log('📥 TOPIC SUBMISSION - Response ok:', response.ok);

        if (response.ok) {
            const responseData = await response.text();
            console.log('✅ TOPIC SUBMISSION - Response data:', responseData);

            toast('Topic Selected!', {
                description: 'Your project topic has been set. You can now submit it for supervisor approval.',
            });

            console.log('🔄 TOPIC SUBMISSION - Redirecting to:', route('projects.show', props.project.slug));
            // Redirect to project show page or approval flow
            router.visit(route('projects.show', props.project.slug));
        } else {
            const errorData = await response.text();
            console.error('❌ TOPIC SUBMISSION - Error response:', errorData);
            throw new Error(`HTTP ${response.status}: ${errorData}`);
        }
    } catch (error) {
        console.error('💥 TOPIC SUBMISSION - Exception:', error);
        toast('Error', {
            description: 'Failed to select topic. Please try again.',
        });
    } finally {
        isSelecting.value = false;
        console.log('🏁 TOPIC SUBMISSION - Finished (isSelecting set to false)');
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

// Cleanup on unmount
onUnmounted(() => {
    if (eventSource.value) {
        eventSource.value.close();
        eventSource.value = null;
    }
});
</script>

<template>
    <AppLayout title="Select Project Topic">
        <div class="mx-auto max-w-4xl space-y-8 p-6">
            <!-- Back Navigation -->
            <div class="flex items-center justify-between">
                <Button @click="goBackToWizard" variant="ghost" size="sm" class="text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Project Setup
                </Button>
            </div>

            <!-- Header -->
            <div class="space-y-3 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                    <Lightbulb class="h-8 w-8 text-primary" />
                </div>
                <h1 class="text-3xl font-bold">Choose Your Project Topic</h1>
                <p class="mx-auto max-w-2xl text-muted-foreground">
                    Select or generate a research topic for your {{ project.type }} {{ project.field_of_study }} project. This will be the foundation
                    of your entire academic work.
                </p>
            </div>

            <!-- Project Context -->
            <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BookOpen class="h-5 w-5" />
                        Project Context
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-6 text-sm">
                    <div>
                        <Label class="text-muted-foreground">Field of Study</Label>
                        <p class="font-semibold">{{ project.field_of_study }}</p>
                    </div>
                    <div>
                        <Label class="text-muted-foreground">Academic Level</Label>
                        <Badge variant="outline" class="capitalize">{{ project.type }}</Badge>
                    </div>
                    <div>
                        <Label class="text-muted-foreground">University</Label>
                        <p class="font-semibold">{{ project.university }}</p>
                    </div>
                    <div>
                        <Label class="text-muted-foreground">Course</Label>
                        <p class="font-semibold">{{ project.course }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Topic Selection Tabs -->
            <Tabs v-model="activeTab" class="space-y-6">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="existing">Enter Topic</TabsTrigger>
                    <TabsTrigger value="generated">AI Generated Topics</TabsTrigger>
                </TabsList>

                <!-- Enter Existing Topic -->
                <TabsContent value="existing" class="space-y-6">
                    <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                        <CardHeader>
                            <CardTitle>Enter Your Project Topic</CardTitle>
                            <CardDescription>
                                If you already have a project topic (approved by supervisor or from your own research), enter it below.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="custom-title">Project Title</Label>
                                <Input id="custom-title" v-model="customTitle" placeholder="A concise title for your project..." class="text-sm" />
                            </div>

                            <div class="space-y-2">
                                <Label for="custom-topic">Research Topic</Label>
                                <Textarea
                                    id="custom-topic"
                                    v-model="customTopic"
                                    placeholder="Enter your research topic or question..."
                                    rows="3"
                                    class="resize-none text-sm"
                                />
                                <p class="text-xs text-muted-foreground">The main research topic or question you want to investigate.</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="custom-description">Project Description</Label>
                                <Textarea
                                    id="custom-description"
                                    v-model="customDescription"
                                    placeholder="Describe your research topic, problem statement, and what you plan to investigate..."
                                    rows="6"
                                    class="resize-none text-sm"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Provide a detailed description of your research focus, objectives, and scope.
                                </p>
                            </div>

                            <Button @click="submitTopic" :disabled="(!customTopic.trim() && !customDescription.trim()) || isSelecting" class="w-full">
                                <Loader2 v-if="isSelecting" class="mr-2 h-4 w-4 animate-spin" />
                                <ArrowRight v-else class="mr-2 h-4 w-4" />
                                {{ isSelecting ? 'Setting Topic...' : 'Continue with This Topic' }}
                            </Button>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- AI Generated Topics -->
                <TabsContent value="generated" class="space-y-6">
                    <Card class="border-[0.5px] border-border/50 shadow-[0_1px_3px_0_rgba(0,0,0,0.1),0_1px_2px_-1px_rgba(0,0,0,0.1)]">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Lightbulb class="h-5 w-5" />
                                AI Generated Topic Suggestions
                            </CardTitle>
                            <CardDescription>
                                Let our AI suggest research topics tailored to your field of study and academic level.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex gap-3">
                                <Button @click="generateTopics" :disabled="isGenerating" variant="default">
                                    <Loader2 v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                                    <Lightbulb v-else class="mr-2 h-4 w-4" />
                                    {{
                                        isGenerating ? 'Generating Topics...' : generatedTopics.length > 0 ? 'Generate New Topics' : 'Generate Topics'
                                    }}
                                </Button>

                                <Button v-if="generatedTopics.length > 0" @click="generateTopics" :disabled="isGenerating" variant="outline">
                                    <RefreshCw class="mr-2 h-4 w-4" />
                                    Refresh
                                </Button>
                            </div>

                            <!-- Topic Filters -->
                            <div v-if="generatedTopics.length > 0" class="flex gap-4 rounded-lg bg-muted/30 p-4">
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs font-medium">Difficulty:</Label>
                                    <select v-model="difficultyFilter" class="rounded border bg-background px-2 py-1 text-xs">
                                        <option value="all">All Levels</option>
                                        <option value="beginner">Beginner Friendly</option>
                                        <option value="intermediate">Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs font-medium">Timeline:</Label>
                                    <select v-model="timelineFilter" class="rounded border bg-background px-2 py-1 text-xs">
                                        <option value="all">Any Duration</option>
                                        <option value="6-9 months">6-9 months</option>
                                        <option value="9-12 months">9-12 months</option>
                                        <option value="12+ months">12+ months</option>
                                    </select>
                                </div>
                                <div class="ml-auto flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs">{{ filteredTopics.length }} topics</Badge>
                                </div>
                            </div>

                            <!-- Enhanced Topics List -->
                            <div v-if="generatedTopics.length > 0" class="space-y-3">
                                <h4 class="flex items-center gap-2 text-sm font-medium">
                                    Choose a topic that interests you:
                                    <Badge variant="secondary" class="text-xs">Sorted by feasibility</Badge>
                                </h4>
                                <div class="max-h-[600px] space-y-4 overflow-y-auto">
                                    <div
                                        v-for="topic in filteredTopics"
                                        :key="topic.id"
                                        class="rounded-xl border border-border/50 bg-card p-5 transition-all duration-200 hover:shadow-md"
                                    >
                                        <div class="space-y-4">
                                            <!-- Topic Title & Select Button -->
                                            <div class="flex items-start justify-between gap-4">
                                                <h3 class="flex-1 text-base leading-relaxed font-semibold text-foreground">
                                                    {{ topic.title }}
                                                </h3>
                                                <Button size="sm" variant="default" class="shrink-0" @click="selectGeneratedTopic(topic)">
                                                    <CheckCircle class="mr-2 h-4 w-4" />
                                                    Select
                                                </Button>
                                            </div>

                                            <!-- Topic Description -->
                                            <div class="space-y-2">
                                                <div class="text-sm leading-relaxed text-muted-foreground">
                                                    <p v-if="!expandedTopics.has(topic.id)">
                                                        {{ truncateDescription(topic.description) }}
                                                    </p>
                                                    <p v-else>
                                                        {{ topic.description }}
                                                    </p>
                                                </div>

                                                <!-- Read More/Less Button -->
                                                <button
                                                    v-if="isDescriptionTruncated(topic.description)"
                                                    @click="toggleDescription(topic.id)"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary transition-colors hover:text-primary/80"
                                                >
                                                    <span>{{ expandedTopics.has(topic.id) ? 'Read less' : 'Read more' }}</span>
                                                    <ChevronDown v-if="!expandedTopics.has(topic.id)" class="h-3 w-3" />
                                                    <ChevronUp v-else class="h-3 w-3" />
                                                </button>
                                            </div>

                                            <!-- Topic Metadata -->
                                            <div class="flex flex-wrap items-center gap-2">
                                                <Badge :variant="getDifficultyVariant(topic.difficulty)" class="text-xs">
                                                    {{ topic.difficulty }}
                                                </Badge>
                                                <Badge variant="outline" class="text-xs"> 📅 {{ topic.timeline }} </Badge>
                                                <Badge :variant="getResourceVariant(topic.resource_level)" class="text-xs">
                                                    🔧 {{ topic.resource_level }} Resources
                                                </Badge>
                                                <Badge variant="secondary" class="text-xs"> 🔬 {{ topic.research_type }} </Badge>

                                                <!-- Feasibility Score -->
                                                <div class="ml-auto flex items-center gap-2">
                                                    <span class="text-xs text-muted-foreground">Feasibility:</span>
                                                    <div class="h-2 w-20 rounded-full bg-muted">
                                                        <div
                                                            class="h-2 rounded-full transition-all"
                                                            :class="
                                                                topic.feasibility_score >= 80
                                                                    ? 'bg-green-500'
                                                                    : topic.feasibility_score >= 60
                                                                      ? 'bg-yellow-500'
                                                                      : 'bg-red-500'
                                                            "
                                                            :style="`width: ${topic.feasibility_score}%`"
                                                        ></div>
                                                    </div>
                                                    <span class="text-xs font-medium">{{ topic.feasibility_score }}%</span>
                                                </div>
                                            </div>

                                            <!-- Keywords -->
                                            <div class="flex flex-wrap gap-2">
                                                <span
                                                    v-for="keyword in topic.keywords"
                                                    :key="keyword"
                                                    class="rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                                >
                                                    #{{ keyword }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div v-else-if="isGenerating" class="space-y-4 py-12 text-center">
                                <Loader2 class="mx-auto mb-4 h-8 w-8 animate-spin text-primary" />
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-foreground">
                                        {{ generationProgress || 'AI is generating personalized topics for your project...' }}
                                    </p>

                                    <!-- Show streaming content if available -->
                                    <div v-if="streamingContent" class="mx-auto max-w-2xl rounded-lg bg-muted/50 p-4 text-left">
                                        <p class="mb-2 text-xs text-muted-foreground">Live Generation Preview:</p>
                                        <p class="text-sm whitespace-pre-wrap text-foreground">{{ streamingContent }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="rounded-lg border-2 border-dashed border-border/50 py-12 text-center">
                                <Lightbulb class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="mb-4 text-sm text-muted-foreground">
                                    Click "Generate Topics" to get AI-powered suggestions tailored to your field of study.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
