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
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft, ArrowRight, BookOpen, CheckCircle, FileText, GraduationCap, Lightbulb, Loader2, MessageSquare, RefreshCw, School } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import PurchaseModal from '@/components/PurchaseModal.vue';
import { useWordBalance } from '@/composables/useWordBalance';

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

const {
    balance,
    showPurchaseModal,
    requiredWordsForModal,
    actionDescriptionForModal,
    closePurchaseModal,
} = useWordBalance();

const minimumTopicBalance = 300;
const geographicFocus = ref<'nigeria_west_africa' | 'balanced' | 'global'>('balanced');

onMounted(() => {
    // Debug: Log what data we received from the backend
    console.log('📋 TOPIC SELECTION - Project data received:', {
        topic: props.project.topic,
        title: props.project.title,
        description: props.project.description,
        description_exists: !!props.project.description,
        description_length: props.project.description?.length || 0
    });

    // If user already has a topic, show it
    if (props.project.topic) {
        customTopic.value = props.project.topic;
        customDescription.value = props.project.description || '';
        customTitle.value = props.project.title || '';
        activeTab.value = 'existing';

        console.log('✅ TOPIC SELECTION - Form fields populated:', {
            customTopic: customTopic.value?.substring(0, 50),
            customDescription: customDescription.value?.substring(0, 50),
            customTitle: customTitle.value
        });
    }

    // If we have saved topics, load them and switch to generated tab
    if (props.savedTopics && props.savedTopics.length > 0) {
        generatedTopics.value = props.savedTopics;
        console.log('📦 SAVED TOPICS - Loaded from database', {
            count: props.savedTopics.length,
            topics: props.savedTopics.map(t => t.title)
        });

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
const progressSteps: Record<string, { order: number, label: string }> = {
    'connecting': { order: 1, label: 'Connecting to AI service' },
    'analyzing': { order: 2, label: 'Analyzing your project context' },
    'generating': { order: 3, label: 'Generating research topics' },
    'enriching': { order: 4, label: 'Enriching with metadata' },
    'complete': { order: 5, label: 'Complete' }
};

const getStepIndicatorClass = (step: string) => {
    const currentStepOrder = progressSteps[currentProgressStep.value]?.order || 0;
    const stepOrder = progressSteps[step]?.order || 0;

    if (stepOrder < currentStepOrder) {
        return 'h-3 w-3 rounded-full bg-green-500'; // Completed
    } else if (stepOrder === currentStepOrder) {
        return 'h-3 w-3 rounded-full bg-primary animate-pulse'; // Current
    } else {
        return 'h-3 w-3 rounded-full bg-muted'; // Pending
    }
};

const getProgressPercentage = () => {
    const currentStepOrder = progressSteps[currentProgressStep.value]?.order || 0;
    const totalSteps = Object.keys(progressSteps).length;
    return Math.min((currentStepOrder / totalSteps) * 100, 100);
};

const generateTopics = async () => {
    console.log('🚀 TOPIC GENERATION - Starting streaming topic generation');

    if (balance.value < minimumTopicBalance) {
        requiredWordsForModal.value = minimumTopicBalance;
        actionDescriptionForModal.value = 'generate research topics';
        showPurchaseModal.value = true;
        console.warn('❌ TOPIC GENERATION - Insufficient word balance', {
            current_balance: balance.value,
            required: minimumTopicBalance
        });
        return;
    }

    isGenerating.value = true;
    generatedTopics.value = [];
    let streamClosed = false;

    try {
        // Initialize progress tracking
        currentProgressStep.value = 'connecting';
        generationProgress.value = 'Connecting to AI service...';

        // Start Server-Sent Events connection for real-time progress
        const streamUrl =
            route('topics.stream', props.project.slug) +
            `?regenerate=true&geographic_focus=${encodeURIComponent(geographicFocus.value)}`;
        console.log('📡 TOPIC GENERATION - Connecting to stream:', streamUrl);

        const eventSource = new EventSource(streamUrl);

        eventSource.onopen = () => {
            console.log('📡 SSE - Connection opened');
            currentProgressStep.value = 'connecting';
            generationProgress.value = 'Connected to AI service - analyzing your project...';
        };

        eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                console.log('📡 SSE - Message received:', data.type, data);

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
                        if (data.context) {
                            console.log('🎓 Academic context:', data.context);
                        }
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
                            currentProgressStep.value = 'enriching';
                            generationProgress.value = 'Loading topics from cache for faster response...';
                        }
                        break;

                    case 'complete':
                        currentProgressStep.value = 'complete';
                        if (data.topics && Array.isArray(data.topics)) {
                            generatedTopics.value = data.topics;
                            generationProgress.value = `✓ Successfully generated ${data.topics.length} personalized research topics!`;
                            activeTab.value = 'generated';

                            toast('Success', {
                                description: `${data.topics.length} research topics generated successfully!`,
                            });
                        } else {
                            generationProgress.value = data.message || 'Topics generated successfully!';
                        }
                        break;

                    case 'error': {
                        const message = data.message || 'Stream error occurred';
                        generationProgress.value = message;
                        toast('Error', {
                            description: message,
                        });
                        streamClosed = true;
                        eventSource.close();
                        isGenerating.value = false;
                        break;
                    }

                    case 'end':
                        console.log('📡 SSE - Stream ended');
                        streamClosed = true;
                        eventSource.close();
                        isGenerating.value = false;
                        break;
                }
            } catch (parseError) {
                console.error('📡 SSE - Failed to parse message:', parseError, event.data);
            }
        };

        eventSource.onerror = (error) => {
            if (streamClosed) {
                console.log('📡 SSE - Ignoring connection error after stream closed');
                return;
            }

            console.error('📡 SSE - Connection error:', error);
            eventSource.close();

            streamClosed = true;

            // Check if we already have topics (partial success)
            if (generatedTopics.value.length > 0) {
                generationProgress.value = `✓ Generated ${generatedTopics.value.length} topics (connection ended early)`;
                activeTab.value = 'generated';
                toast('Partial Success', {
                    description: `${generatedTopics.value.length} topics generated before connection ended.`,
                });
            } else {
                // Fallback to regular endpoint if streaming fails
                console.log('📡 TOPIC GENERATION - Falling back to regular endpoint');
                generateTopicsWithFallback().catch(fallbackError => {
                    console.error('Fallback also failed:', fallbackError);
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
            if (!isGenerating.value || streamClosed) {
                return;
            }

            console.log('⏰ TOPIC GENERATION - Timeout reached, closing stream');
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
        }, 300000); // 5 minutes timeout

    } catch (error: any) {
        console.error('💥 TOPIC GENERATION - Error:', error);
        generationProgress.value = 'Failed to generate topics. Please try again.';

        toast('Error', {
            description: error.message || 'Failed to generate topics. Please try again.',
        });

        isGenerating.value = false;
    }
};

// Fallback to regular endpoint if streaming fails
const generateTopicsWithFallback = async () => {
    console.log('🔄 TOPIC GENERATION - Using fallback method');
    generationProgress.value = 'Retrying with fallback method...';

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
                geographic_focus: geographicFocus.value,
            }),
            signal: controller.signal,
        });

        clearTimeout(timeoutId);

        if (response.status === 402) {
            const errorData = await response.json().catch(() => null);
            requiredWordsForModal.value = errorData?.required ?? minimumTopicBalance;
            actionDescriptionForModal.value = 'generate research topics';
            showPurchaseModal.value = true;
            generationProgress.value = errorData?.message || 'Insufficient word balance to generate topics.';
            toast('Error', {
                description: generationProgress.value,
            });
            isGenerating.value = false;
            return;
        }

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
            generatedTopics.value = data.topics;
            generationProgress.value = `✓ Successfully generated ${data.topics.length} research topics!`;
            activeTab.value = 'generated';

            toast('Success', {
                description: `${data.topics.length} research topics generated successfully!`,
            });
        } else {
            throw new Error('Invalid response format: topics array not found');
        }

    } catch (error: any) {
        console.error('💥 FALLBACK GENERATION - Error:', error);
        generationProgress.value = 'Failed to generate topics. Please try again.';

        toast('Error', {
            description: error.message || 'Failed to generate topics. Please try again.',
        });
    }
};

const selectGeneratedTopic = (topic: Topic) => {
    selectedTopic.value = topic.title;
    selectedDescription.value = topic.description;
    
    // Strip HTML for inputs that expect plain text (or close to it)
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = topic.title;
    const plainTitle = tempDiv.textContent || tempDiv.innerText || topic.title;

    customTopic.value = plainTitle; // Use plain text for the short topic field
    customDescription.value = topic.description; // Keep HTML for the rich text editor
    
    customTitle.value = generateTitleFromTopic(plainTitle);
    activeTab.value = 'existing';
};

const generateTitleFromTopic = (topic: string): string => {
    // Strip HTML if present
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = topic;
    const plainText = (tempDiv.textContent || tempDiv.innerText || topic).trim();
    const maxLength = 255; // Align with database column length to avoid backend errors

    return plainText.length > maxLength ? plainText.slice(0, maxLength) : plainText;
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

const openTopicInLab = (topic: { title: string; description: string }) => {
    // Ensure a clean title for the lab view
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = topic.title;
    const plainTitle = (tempDiv.textContent || tempDiv.innerText || topic.title).trim();

    if (!plainTitle) {
        toast('Error', { description: 'Topic title is missing.' });
        return;
    }

    router.visit(
        route('topics.lab', {
            project: props.project.slug,
            new: 'true',
            return_to_selection: 1,
            topic_title: plainTitle,
            topic_description: topic.description || '',
        }),
    );
};

const refineCurrentTopicInLab = () => {
    const title = (customTopic.value || customTitle.value || '').trim();
    const description = (customDescription.value || '').trim();
    if (!title && !description) {
        toast('Error', { description: 'Please select or enter a topic first.' });
        return;
    }

    openTopicInLab({
        title: title || customTitle.value || 'Research Topic',
        description,
    });
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
        project_slug: props.project.slug,
        current_url: window.location.href,
        timestamp: new Date().toISOString(),
    });

    console.log('🔍 TOPIC SUBMISSION - Full form data:', {
        customTopic: customTopic.value,
        customDescription: customDescription.value,
        customTitle: customTitle.value,
        customTopic_length: customTopic.value?.length || 0,
        customDescription_length: customDescription.value?.length || 0,
        customTitle_length: customTitle.value?.length || 0,
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
            topic: customTopic.value.trim() || customTitle.value.trim() || 'Research Topic',
            title: customTitle.value.trim() || generateTitleFromTopic(customTopic.value || customDescription.value),
            description: customDescription.value.trim(),
        };

        console.log('📤 TOPIC SUBMISSION - Request data:', requestData);
        console.log('📍 TOPIC SUBMISSION - Request URL:', route('topics.select', props.project.slug));

        // Get CSRF token and validate it exists
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('🔐 TOPIC SUBMISSION - CSRF token found:', !!csrfToken);
        console.log('🔐 TOPIC SUBMISSION - CSRF token preview:', csrfToken?.substring(0, 10) + '...');

        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page and try again.');
        }

        console.log('📡 TOPIC SUBMISSION - Making fetch request...');
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

        console.log('📥 TOPIC SUBMISSION - Response received');
        console.log('📥 TOPIC SUBMISSION - Response status:', response.status);
        console.log('📥 TOPIC SUBMISSION - Response statusText:', response.statusText);
        console.log('📥 TOPIC SUBMISSION - Response ok:', response.ok);
        console.log('📥 TOPIC SUBMISSION - Response headers:', Object.fromEntries(response.headers.entries()));

        if (response.ok) {
            console.log('✅ TOPIC SUBMISSION - Success! Processing response...');
            const responseData = await response.json();
            console.log('✅ TOPIC SUBMISSION - Response data:', responseData);

            toast('Topic Selected!', {
                description: 'Your project topic has been set. You can now submit it for supervisor approval.',
            });

            console.log('🔄 TOPIC SUBMISSION - Letting middleware handle redirect');
            console.log('🔄 TOPIC SUBMISSION - Current project slug:', props.project.slug);
            console.log('🔄 TOPIC SUBMISSION - Target route:', route('projects.show', props.project.slug));

            // Let the middleware handle the redirect to the appropriate next step
            // The middleware will redirect to topic-approval based on the updated project status
            router.visit(route('projects.show', props.project.slug), {
                method: 'get',
                replace: true
            });
        } else {
            console.error('❌ TOPIC SUBMISSION - Request failed');
            console.error('❌ TOPIC SUBMISSION - Status:', response.status);
            console.error('❌ TOPIC SUBMISSION - StatusText:', response.statusText);

            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                console.error('❌ TOPIC SUBMISSION - Error JSON:', errorData);
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                // Response is not JSON, likely HTML error page
                const errorText = await response.text();
                console.error('❌ TOPIC SUBMISSION - Error HTML (first 500 chars):', errorText.substring(0, 500));

                if (response.status === 419) {
                    errorMessage = 'Session expired. Please refresh the page and try again.';
                } else if (errorText.includes('<!DOCTYPE')) {
                    errorMessage = 'Server returned an error page instead of data. Please try again.';
                }
            }
            throw new Error(errorMessage);
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

<style scoped>
:deep(.prose h2) {
    color: var(--primary);
    font-size: 1.25rem;
    font-weight: 700;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}

:deep(.prose h3) {
    color: var(--foreground);
    font-size: 1.1rem;
    font-weight: 600;
}

:deep(.prose strong) {
    color: var(--foreground);
    font-weight: 700;
}

:deep(.prose p) {
    margin-bottom: 0.75em;
    line-height: 1.6;
}
</style>

<template>
    <AppLayout title="Select Project Topic">
        <div class="min-h-screen bg-gradient-to-b from-background via-background/95 to-muted/20">
            <div class="mx-auto max-w-7xl space-y-10 p-6 pb-20 lg:p-10">
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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Field of Study -->
                        <Card class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-blue-500/10 group-hover:bg-blue-500/20 transition-colors blur-2xl"></div>
                            <div class="p-6 relative">
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500 group-hover:scale-110 group-hover:bg-blue-500/20 transition-all duration-300">
                                    <BookOpen class="h-5 w-5" />
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Field of Study</span>
                                    <div class="font-bold text-lg leading-tight text-foreground">{{ project.field_of_study }}</div>
                                </div>
                            </div>
                        </Card>

                        <!-- Academic Level -->
                        <Card class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-purple-500/10 group-hover:bg-purple-500/20 transition-colors blur-2xl"></div>
                            <div class="p-6 relative">
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500 group-hover:scale-110 group-hover:bg-purple-500/20 transition-all duration-300">
                                    <GraduationCap class="h-5 w-5" />
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Academic Level</span>
                                    <div class="font-bold text-lg leading-tight text-foreground capitalize">{{ project.type }}</div>
                                </div>
                            </div>
                        </Card>

                        <!-- University -->
                        <Card class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-orange-500/10 group-hover:bg-orange-500/20 transition-colors blur-2xl"></div>
                            <div class="p-6 relative">
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500/10 text-orange-500 group-hover:scale-110 group-hover:bg-orange-500/20 transition-all duration-300">
                                    <School class="h-5 w-5" />
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">University</span>
                                    <div class="font-bold text-lg leading-tight text-foreground" :title="project.full_university_name || project.university">
                                        {{ project.full_university_name || project.university }}
                                    </div>
                                </div>
                            </div>
                        </Card>

                        <!-- Course -->
                        <Card class="group relative overflow-hidden border-border/50 bg-card/50 backdrop-blur-sm transition-all duration-300 hover:shadow-lg hover:shadow-primary/5 hover:border-primary/20 hover:-translate-y-1">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-green-500/10 group-hover:bg-green-500/20 transition-colors blur-2xl"></div>
                            <div class="p-6 relative">
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-green-500/10 text-green-500 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-300">
                                    <FileText class="h-5 w-5" />
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Course</span>
                                    <div class="font-bold text-lg leading-tight text-foreground">{{ project.course }}</div>
                                </div>
                            </div>
                        </Card>
                    </div>
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
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <!-- Project Details Card -->
                                <Card class="lg:col-span-1 border-border/50 shadow-lg shadow-primary/5 overflow-hidden group flex flex-col">
                                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/5 transition-colors blur-3xl pointer-events-none"></div>
                                    <CardHeader class="pb-2 border-b border-border/40 bg-muted/10">
                                        <CardTitle class="flex items-center gap-2 text-lg">
                                            <div class="p-1.5 rounded-md bg-primary/10 text-primary">
                                                <Lightbulb class="h-4 w-4" />
                                            </div>
                                            Project Details
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-6 pt-6 flex-1">
                                        <div class="space-y-3">
                                            <Label for="custom-title" class="text-sm font-medium text-foreground/80">Project Title</Label>
                                            <Input id="custom-title" v-model="customTitle"
                                                placeholder="A concise title..."
                                                :maxlength="255"
                                                class="h-12 bg-muted/30 border-border/50 focus:bg-background transition-all" />
                                        </div>

                                        <div class="space-y-3">
                                            <Label for="custom-topic" class="text-sm font-medium text-foreground/80">Research Topic</Label>
                                            <Textarea id="custom-topic" v-model="customTopic"
                                                placeholder="Enter your main research topic or question..."
                                                rows="4"
                                                class="resize-none bg-muted/30 border-border/50 focus:bg-background transition-all" />
                                            <p class="text-xs text-muted-foreground">The main topic you want to investigate.</p>
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Description Card -->
                                <Card class="lg:col-span-2 border-border/50 shadow-lg shadow-primary/5 overflow-hidden group flex flex-col">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary/50 to-transparent opacity-20"></div>
                                    <CardHeader class="pb-2 border-b border-border/40 bg-muted/10">
                                        <CardTitle class="flex items-center gap-2 text-lg">
                                            <div class="p-1.5 rounded-md bg-primary/10 text-primary">
                                                <MessageSquare class="h-4 w-4" />
                                            </div>
                                            Detailed Description
                                        </CardTitle>
                                        <CardDescription>
                                            Provide a comprehensive description of your research.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="space-y-6 pt-6 flex-1 flex flex-col">
                                        <div class="space-y-3 flex-1 flex flex-col">
                                            <Label for="custom-description" class="sr-only">Project Description</Label>
                                            <div class="flex-1 border rounded-md overflow-hidden bg-background">
                                                <RichTextEditor 
                                                    v-model="customDescription"
                                                    placeholder="Describe your research focus, objectives, problem statement, and scope in detail..."
                                                    min-height="300px"
                                                    :show-toolbar="true"
                                                />
                                            </div>
                                        </div>

                                        <div class="pt-4 mt-auto">
                                            <Button @click="submitTopic"
                                                :disabled="(!customTopic.trim() && !customDescription.trim()) || isSelecting"
                                                class="w-full h-12 text-base font-medium shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all">
                                                <Loader2 v-if="isSelecting" class="mr-2 h-5 w-5 animate-spin" />
                                                <span v-else class="flex items-center">
                                                    Continue with This Topic
                                                    <ArrowRight class="ml-2 h-5 w-5" />
                                                </span>
                                            </Button>

                                            <Button
                                                @click="refineCurrentTopicInLab"
                                                :disabled="(!customTopic.trim() && !customDescription.trim()) || isSelecting"
                                                variant="outline"
                                                class="w-full h-12 text-base font-medium mt-3"
                                            >
                                                <MessageSquare class="mr-2 h-5 w-5" />
                                                Refine in Topic Lab
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
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
	                                            <div class="flex items-center gap-2 bg-background/50 p-1 rounded-lg border border-border/50">
	                                                <span class="text-xs font-medium px-2 text-muted-foreground">Focus</span>
	                                                <select v-model="geographicFocus"
	                                                    class="h-8 rounded-md border-0 bg-transparent text-xs font-medium focus:ring-0 cursor-pointer hover:bg-muted/50 transition-colors">
	                                                    <option value="balanced">Balanced</option>
	                                                    <option value="nigeria_west_africa">Nigeria / West Africa</option>
	                                                    <option value="global">Global</option>
	                                                </select>
	                                            </div>
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
                                        class="mt-6 flex flex-wrap items-center gap-4 animate-in fade-in slide-in-from-top-2">
                                        <div
                                            class="flex items-center gap-2 bg-background/50 p-1 rounded-lg border border-border/50">
                                            <span
                                                class="text-xs font-medium px-2 text-muted-foreground">Difficulty</span>
                                            <select v-model="difficultyFilter"
                                                class="h-8 rounded-md border-0 bg-transparent text-xs font-medium focus:ring-0 cursor-pointer hover:bg-muted/50 transition-colors">
                                                <option value="all">All Levels</option>
                                                <option value="beginner">Beginner Friendly</option>
                                                <option value="intermediate">Intermediate</option>
                                                <option value="advanced">Advanced</option>
                                            </select>
                                        </div>
                                        <div
                                            class="flex items-center gap-2 bg-background/50 p-1 rounded-lg border border-border/50">
                                            <span class="text-xs font-medium px-2 text-muted-foreground">Timeline</span>
                                            <select v-model="timelineFilter"
                                                class="h-8 rounded-md border-0 bg-transparent text-xs font-medium focus:ring-0 cursor-pointer hover:bg-muted/50 transition-colors">
                                                <option value="all">Any Duration</option>
                                                <option value="6-9 months">6-9 months</option>
                                                <option value="9-12 months">9-12 months</option>
                                                <option value="12+ months">12+ months</option>
                                            </select>
                                        </div>
                                        <div class="ml-auto">
                                            <Badge variant="secondary" class="h-8 px-3 text-xs font-medium">
                                                {{ filteredTopics.length }} results
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
                                    <div v-else class="p-6 flex flex-col gap-8">
                                        <div v-for="topic in filteredTopics" :key="topic.id" 
                                            class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in slide-in-from-bottom-2 duration-500">
                                            
                                            <!-- Metadata Card -->
                                            <div class="lg:col-span-1 group relative flex flex-col rounded-xl border border-border/50 bg-card transition-all duration-300 hover:shadow-xl hover:shadow-primary/5 hover:border-primary/30 hover:-translate-y-1 overflow-hidden"
                                                :class="selectedTopic === topic.title ? 'ring-2 ring-primary border-primary bg-primary/5' : ''">
                                                
                                                <!-- Selection Overlay (Active State) -->
                                                <div v-if="selectedTopic === topic.title" class="absolute top-0 right-0 p-0 z-10">
                                                    <div class="bg-primary text-primary-foreground rounded-bl-xl px-3 py-1.5 shadow-sm flex items-center gap-1.5">
                                                        <CheckCircle class="h-3.5 w-3.5" />
                                                        <span class="text-[10px] font-bold uppercase tracking-wider">Selected</span>
                                                    </div>
                                                </div>

                                                <div class="p-6 flex flex-col gap-4 flex-1">
                                                    <!-- Title -->
                                                    <SafeHtmlText :content="topic.title" as="h3" class="text-lg font-bold leading-tight text-foreground group-hover:text-primary transition-colors" />

                                                    <!-- Badges -->
                                                    <div class="flex flex-wrap gap-2">
                                                        <Badge :variant="getDifficultyVariant(topic.difficulty)" class="text-[10px] uppercase tracking-wider font-semibold py-0.5">
                                                            {{ topic.difficulty }}
                                                        </Badge>
                                                        <Badge variant="outline" class="text-[10px] uppercase tracking-wider font-medium border-border text-muted-foreground">
                                                            {{ topic.timeline }}
                                                        </Badge>
                                                        <Badge variant="secondary" class="text-[10px] uppercase tracking-wider font-medium bg-muted/50">
                                                            {{ topic.research_type }}
                                                        </Badge>
                                                    </div>

                                                    <!-- Keywords -->
                                                    <div class="flex flex-wrap gap-1.5 pt-2">
                                                        <span v-for="keyword in topic.keywords" :key="keyword"
                                                            class="text-[10px] font-medium text-muted-foreground bg-muted/30 px-2 py-1 rounded-md border border-border/30">
                                                            #{{ keyword }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-auto pt-4 space-y-4">
                                                        <!-- Feasibility -->
                                                        <div class="flex items-center gap-3 bg-muted/20 p-3 rounded-lg border border-border/30">
                                                            <div class="relative h-10 w-10 flex items-center justify-center shrink-0">
                                                                <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                                                                    <path class="text-muted/30" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                                                    <path :class="topic.feasibility_score >= 80 ? 'text-green-500' : topic.feasibility_score >= 60 ? 'text-yellow-500' : 'text-red-500'"
                                                                        :stroke-dasharray="`${topic.feasibility_score}, 100`"
                                                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                        fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                                                </svg>
                                                                <span class="absolute text-[10px] font-bold">{{ topic.feasibility_score }}%</span>
                                                            </div>
                                                            <div class="flex flex-col">
                                                                <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Feasibility</span>
                                                                <span class="text-xs font-medium" :class="topic.feasibility_score >= 80 ? 'text-green-600' : topic.feasibility_score >= 60 ? 'text-yellow-600' : 'text-red-600'">
                                                                    {{ topic.feasibility_score >= 80 ? 'High' : topic.feasibility_score >= 60 ? 'Medium' : 'Low' }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <!-- Action Button -->
                                                        <Button @click="selectGeneratedTopic(topic)"
                                                            :variant="selectedTopic === topic.title ? 'secondary' : 'default'"
                                                            class="w-full shadow-sm transition-all duration-300"
                                                            :class="selectedTopic === topic.title ? 'bg-green-100 text-green-700 hover:bg-green-200 border-green-200' : 'hover:shadow-md hover:scale-[1.02]'">
                                                            {{ selectedTopic === topic.title ? 'Selected' : 'Select This Topic' }}
                                                            <CheckCircle v-if="selectedTopic === topic.title" class="ml-2 h-3.5 w-3.5" />
                                                            <ArrowRight v-else class="ml-2 h-3.5 w-3.5" />
                                                        </Button>

                                                        <Button
                                                            @click="openTopicInLab(topic)"
                                                            variant="outline"
                                                            class="w-full mt-2"
                                                        >
                                                            <MessageSquare class="mr-2 h-4 w-4" />
                                                            Refine in Topic Lab
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Description Card -->
                                            <div class="lg:col-span-2 group relative flex flex-col rounded-xl border border-border/50 bg-card/50 transition-all duration-300 hover:shadow-lg hover:border-primary/20">
                                                <div class="p-6 md:p-8 flex flex-col h-full">
                                                    <div class="flex items-center gap-2 mb-4 pb-4 border-b border-border/30">
                                                        <div class="p-1.5 rounded-md bg-primary/10 text-primary">
                                                            <MessageSquare class="h-4 w-4" />
                                                        </div>
                                                        <span class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Description</span>
                                                    </div>
                                                    
                                                    <div class="prose prose-sm dark:prose-invert max-w-none text-muted-foreground leading-relaxed flex-1">
                                                        <SafeHtmlText :content="topic.description" as="div" />
                                                    </div>
                                                </div>
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

        <PurchaseModal
            :open="showPurchaseModal"
            :current-balance="balance"
            :required-words="requiredWordsForModal"
            :action="actionDescriptionForModal || 'generate research topics'"
            @update:open="(v) => (showPurchaseModal = v)"
            @close="closePurchaseModal"
        />
    </AppLayout>
</template>
```
