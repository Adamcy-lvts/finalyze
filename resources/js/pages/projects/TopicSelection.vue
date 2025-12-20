<!-- /resources/js/pages/projects/TopicSelection.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft, ArrowRight, BookOpen, CheckCircle, FileText, GraduationCap, Lightbulb, Loader2, MessageSquare, RefreshCw, School, Upload } from 'lucide-vue-next';
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
    prefillTopic?: {
        title: string;
        description?: string | null;
    } | null;
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

const stripHtml = (html: string): string => {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

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
        customTopic.value = stripHtml(props.project.topic);
        customDescription.value = props.project.description || '';
        customTitle.value = stripHtml(props.project.title || '');
        activeTab.value = 'existing';

        console.log('✅ TOPIC SELECTION - Form fields populated:', {
            customTopic: customTopic.value?.substring(0, 50),
            customDescription: customDescription.value?.substring(0, 50),
            customTitle: customTitle.value
        });
    }

    // If we were sent here from Topic Lab with a refined topic, prefill Enter Topic tab.
    try {
        const params = new URLSearchParams(window.location.search);
        const prefillTopic = params.get('prefill_topic');
        const prefillTitle = params.get('prefill_title');
        const prefillDescription = params.get('prefill_description');

        if (prefillTopic && prefillTopic.trim()) {
            activeTab.value = 'existing';
            customTopic.value = stripHtml(prefillTopic);
            if (prefillTitle && prefillTitle.trim()) customTitle.value = stripHtml(prefillTitle);
            if (prefillDescription && prefillDescription.trim()) customDescription.value = prefillDescription.trim();

            toast.success('Refined topic loaded', { description: 'Review it in “Enter Topic” then continue.' });

            // Clear query params so refresh doesn't re-apply unexpectedly.
            window.history.replaceState({}, '', route('projects.topic-selection', props.project.slug));
        }
    } catch {
        // ignore
    }

    if (!props.project.topic && !customTopic.value.trim() && props.prefillTopic?.title) {
        activeTab.value = 'existing';
        customTopic.value = stripHtml(props.prefillTopic.title);
        customTitle.value = stripHtml(props.prefillTopic.title);
        if (props.prefillTopic.description) {
            customDescription.value = props.prefillTopic.description;
        }
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

/* Hide scrollbar for horizonal scroll containers */
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-progress-indeterminate {
    animation: shimmer 2s infinite linear;
}
</style>

<template>
    <AppLayout title="Select Project Topic">
        <div class="min-h-screen bg-background relative selection:bg-primary/10 selection:text-primary">
            <!-- Ambient Background Effects -->
            <div class="fixed inset-0 pointer-events-none z-0">
                <div class="absolute top-0 right-0 w-3/4 h-3/4 bg-primary/5 rounded-full blur-[120px] opacity-50"></div>
                <div class="absolute bottom-0 left-0 w-1/2 h-1/2 bg-blue-500/5 rounded-full blur-[100px] opacity-50"></div>
            </div>

            <!-- Compact Header -->
            <header class="sticky top-0 z-40 w-full border-b border-border/40 bg-background/80 backdrop-blur-xl supports-[backdrop-filter]:bg-background/60">
                <div class="container mx-auto flex h-16 items-center justify-between py-4">
                    <div class="flex items-center gap-4">
                        <Button @click="goBackToWizard" variant="ghost" size="icon" class="h-9 w-9 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted/80">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div class="flex flex-col">
                            <h1 class="text-sm font-semibold leading-none flex items-center gap-2">
                                Topic Selection
                                <span class="hidden sm:inline-flex h-1.5 w-1.5 rounded-full bg-primary animate-pulse" v-if="isGenerating"></span>
                            </h1>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Step 2 of 4</p>
                        </div>
                    </div>



                    <!-- Mobile Context Toggle (could be added here if needed, but keeping it simpler for now) -->
                    <div class="lg:hidden">
                        <Badge variant="outline" class="font-normal bg-muted/50">{{ project.type }}</Badge>
                    </div>
                </div>
            </header>

            <main class="container mx-auto max-w-5xl py-8 pb-24 px-4 relative z-10 space-y-8">
                <!-- Header Section -->
                <div class="space-y-4 text-center pb-2">
                    <div class="flex justify-center mb-6">
                        <div class="relative flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-tr from-yellow-500/20 to-orange-500/20 border border-white/10 shadow-[0_0_30px_rgba(234,179,8,0.2)]">
                            <div class="absolute inset-0 rounded-full bg-yellow-500/10 blur-xl"></div>
                            <Lightbulb class="h-8 w-8 text-yellow-400 drop-shadow-[0_0_10px_rgba(250,204,21,0.8)] relative z-10" />
                        </div>
                    </div>
                    <div class="space-y-2">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl bg-gradient-to-br from-foreground via-foreground to-muted-foreground bg-clip-text text-transparent">
                        Choose Your Project Topic
                    </h2>
                    <p class="mx-auto max-w-[600px] text-muted-foreground text-lg leading-relaxed">
                        Define your specific area of interest or let AI help you discover unique project topics.
                    </p>
                    </div>
                </div>

                <!-- Mobile Context Grid (Only visible on small screens) -->
                <div class="lg:hidden grid grid-cols-2 gap-3 pb-4" v-if="project.field_of_study || project.university || project.course">
                    <div class="flex flex-col gap-1 p-3 rounded-lg bg-muted/30 border border-border/50 col-span-2" v-if="project.field_of_study">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium">Field</span>
                        <span class="text-sm font-medium truncate">{{ project.field_of_study }}</span>
                    </div>
                    <div class="flex flex-col gap-1 p-3 rounded-lg bg-muted/30 border border-border/50" v-if="project.university">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium">Institution</span>
                        <span class="text-sm font-medium truncate">{{ project.university }}</span>
                    </div>
                    <div class="flex flex-col gap-1 p-3 rounded-lg bg-muted/30 border border-border/50" v-if="project.course">
                        <span class="text-[10px] uppercase text-muted-foreground font-medium">Course / Dept</span>
                        <span class="text-sm font-medium truncate">{{ project.course }}</span>
                    </div>
                </div>

                <Tabs v-model="activeTab" class="space-y-8">
                    <!-- Custom Centered Tabs List -->
                    <div class="flex justify-center">
                        <TooltipProvider>
                            <TabsList class="grid grid-cols-3 w-full h-auto items-center justify-center rounded-2xl bg-muted/50 p-1.5 text-muted-foreground backdrop-blur-sm border border-border/50 shadow-inner sm:flex sm:w-auto">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <TabsTrigger value="existing" class="rounded-xl px-2 py-2.5 text-[10px] sm:text-sm sm:px-6 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 h-full w-full sm:w-auto">
                                            <FileText class="h-3.5 w-3.5 sm:h-4 sm:w-4 sm:mr-0" />
                                            <span class="truncate sm:hidden">Add Existing</span>
                                            <span class="hidden sm:inline">Add Existing Topic</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Enter a topic you already have in mind</p>
                                    </TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <TabsTrigger value="generated" class="rounded-xl px-2 py-2.5 text-[10px] sm:text-sm sm:px-6 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 h-full w-full sm:w-auto">
                                            <Lightbulb class="h-3.5 w-3.5 sm:h-4 sm:w-4 sm:mr-0" />
                                            <span class="truncate sm:hidden">AI Suggestions</span>
                                            <span class="hidden sm:inline">AI Suggested Topics</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Generate unique topics based on your profile</p>
                                    </TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <TabsTrigger value="import" class="rounded-xl px-2 py-2.5 text-[10px] sm:text-sm sm:px-6 transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 h-full w-full sm:w-auto">
                                            <Upload class="h-3.5 w-3.5 sm:h-4 sm:w-4 sm:mr-0" />
                                            <span class="truncate sm:hidden">Import</span>
                                            <span class="hidden sm:inline">Import Project</span>
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Import a project from an external file</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TabsList>
                        </TooltipProvider>
                    </div>

                    <!-- Manual Entry Tab -->
                    <TabsContent value="existing" class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                            <!-- Left: Core Info -->
                            <div class="lg:col-span-12 xl:col-span-5 space-y-6">
                                <Card class="border-border/50 shadow-lg shadow-primary/5 bg-card/80 backdrop-blur-sm">
                                    <CardHeader>
                                        <CardTitle className="text-lg">Project Title</CardTitle>
                                        <CardDescription>
                                            Keep it clear and academic.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="space-y-6">
                                        <div class="space-y-2">
                                            <Label for="custom-title" class="text-xs font-medium uppercase text-muted-foreground">Title</Label>
                                            <Input id="custom-title" v-model="customTitle"
                                                placeholder="e.g., The Impact of..."
                                                class="h-12 bg-secondary/50 border-border/50 focus:bg-background focus:ring-primary/20 transition-all font-medium" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="custom-topic" class="text-xs font-medium uppercase text-muted-foreground">Research Question / Topic</Label>
                                            <Textarea id="custom-topic" v-model="customTopic"
                                                placeholder="What is the main problem you are investigating?"
                                                rows="5"
                                                class="resize-none bg-secondary/50 border-border/50 focus:bg-background focus:ring-primary/20 transition-all" />
                                        </div>
                                    </CardContent>
                                    <div class="px-6 pb-6 pt-0">
                                         <Button @click="submitTopic" size="lg"
                                            :disabled="(!customTopic.trim() && !customDescription.trim()) || isSelecting"
                                            class="w-full h-12 text-base font-medium shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all">
                                            <Loader2 v-if="isSelecting" class="mr-2 h-5 w-5 animate-spin" />
                                            <span v-else class="flex items-center justify-center w-full">
                                                Confirm Selection
                                                <ArrowRight class="ml-2 h-4 w-4" />
                                            </span>
                                        </Button>
                                    </div>
                                </Card>

                                <div class="hidden xl:block">
                                    <div 
                                        @click="activeTab = 'generated'"
                                        class="rounded-xl bg-orange-500/5 border border-orange-500/10 p-4 cursor-pointer hover:bg-orange-500/10 transition-colors group"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div class="p-2 rounded-full bg-orange-500/10 text-orange-600 dark:text-orange-500 mt-0.5 group-hover:scale-110 transition-transform">
                                                <Lightbulb class="h-4 w-4" />
                                            </div>
                                            <div class="space-y-1">
                                                <h4 class="text-sm font-semibold text-orange-700 dark:text-orange-400">Need Inspiration?</h4>
                                                <p class="text-xs text-muted-foreground leading-relaxed">
                                                    If you're stuck, switch to the <strong>AI Suggestions</strong> tab to generate research topics tailored to your profile.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Description -->
                            <div class="lg:col-span-12 xl:col-span-7 space-y-6">
                                <Card class="border-border/50 shadow-lg shadow-primary/5 bg-card/80 backdrop-blur-sm flex flex-col h-full lg:min-h-[500px]">
                                    <div class="absolute top-0 right-0 p-6 pointer-events-none opacity-20">
                                        <MessageSquare class="w-32 h-32 text-primary" />
                                    </div>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Detailed Description</CardTitle>
                                        <CardDescription>
                                            Elaborate on your research objectives and scope.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="flex-1 min-h-[300px] flex flex-col">
                                        <div class="flex-1 border rounded-xl overflow-hidden bg-secondary/30 focus-within:bg-background focus-within:ring-2 focus-within:ring-primary/20 transition-all">
                                            <RichTextEditor 
                                                v-model="customDescription"
                                                placeholder="Start typing your research description here..."
                                                min-height="300px"
                                                :show-toolbar="true"
                                            />
                                        </div>
                                    </CardContent>
                                    <div class="px-6 pb-6 flex items-center justify-between gap-4">
                                        <p class="text-[10px] text-muted-foreground">
                                            * This description will be used to guide the AI in future steps.
                                        </p>
                                        <Button
                                            @click="refineCurrentTopicInLab"
                                            :disabled="(!customTopic.trim() && !customDescription.trim()) || isSelecting"
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground hover:text-primary"
                                        >
                                            <RefreshCw class="mr-2 h-3.5 w-3.5" />
                                            Refine in Lab
                                        </Button>
                                    </div>
                                </Card>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- AI Generated Tab -->
                    <TabsContent value="generated" class="space-y-6 pb-20 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <!-- Control Bar -->
                        <Card class="border-border/50 bg-card/50 backdrop-blur-sm sticky top-20 z-30 shadow-sm">
                            <div class="p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0 hide-scrollbar">
                                    <div class="flex items-center h-9 px-3 rounded-lg bg-background border border-border/50 text-xs font-medium whitespace-nowrap">
                                        <span class="text-muted-foreground mr-2">Focus:</span>
                                        <Select v-model="geographicFocus">
                                            <SelectTrigger class="h-auto p-0 border-none bg-transparent hover:bg-transparent focus:ring-0 focus:ring-offset-0 gap-1 text-xs font-semibold">
                                                <SelectValue placeholder="Select Focus" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="balanced">Balanced</SelectItem>
                                                <SelectItem value="nigeria_west_africa">West Africa</SelectItem>
                                                <SelectItem value="global">Global</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div v-if="generatedTopics.length > 0" class="h-6 w-[1px] bg-border mx-1 hidden md:block"></div>

                                    <div v-if="generatedTopics.length > 0" class="flex items-center h-9 px-3 rounded-lg bg-background border border-border/50 text-xs font-medium whitespace-nowrap">
                                        <span class="text-muted-foreground mr-2">Timeline:</span>
                                        <select v-model="timelineFilter" class="bg-transparent border-none text-foreground font-semibold focus:ring-0 p-0 cursor-pointer text-xs">
                                            <option value="all">Any</option>
                                            <option value="6-9 months">6-9 mo</option>
                                            <option value="9-12 months">9-12 mo</option>
                                            <option value="12+ months">12+ mo</option>
                                        </select>
                                    </div>
                                </div>

                                <Button @click="generateTopics" :disabled="isGenerating"
                                    size="sm"
                                    :class="generatedTopics.length === 0 ? 'w-full md:w-auto shadow-lg shadow-primary/20' : ''">
                                    <Loader2 v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                                    <RefreshCw v-else-if="generatedTopics.length > 0" class="mr-2 h-4 w-4" />
                                    <Lightbulb v-else class="mr-2 h-4 w-4" />
                                    {{ isGenerating ? 'Thinking...' : generatedTopics.length > 0 ? 'Regenerate' : 'Generate Ideas' }}
                                </Button>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div v-if="isGenerating" class="h-0.5 w-full bg-muted overflow-hidden">
                                <div class="h-full bg-primary animate-progress-indeterminate"></div>
                            </div>
                        </Card>

                        <!-- Results Area -->
                        <div class="min-h-[400px]">
                            <!-- Loading State -->
                            <div v-if="isGenerating" class="flex flex-col items-center justify-center py-20 animate-in fade-in">
                                <div class="bg-primary/10 p-6 rounded-full mb-6 relative">
                                    <Loader2 class="h-10 w-10 text-primary animate-spin" />
                                    <div class="absolute inset-0 rounded-full animate-ping bg-primary/20 opacity-75"></div>
                                </div>
                                <h3 class="text-xl font-semibold mb-2">{{ generationProgress || 'Analyzing your profile...' }}</h3>
                                <p class="text-muted-foreground text-sm max-w-xs text-center">
                                    Our AI is scanning {{ project.field_of_study }} databases for relevant emerging topics.
                                </p>
                            </div>

                            <!-- Empty State -->
                            <div v-else-if="generatedTopics.length === 0" class="flex flex-col items-center justify-center py-20 text-center animate-in fade-in zoom-in-95">
                                <div class="h-32 w-32 rounded-3xl bg-gradient-to-tr from-muted/50 to-muted/10 border border-border/50 flex items-center justify-center mb-6 shadow-xl shadow-black/5 rotate-3 hover:rotate-0 transition-transform duration-500">
                                    <Lightbulb class="h-12 w-12 text-muted-foreground/40" />
                                </div>
                                <h3 class="text-lg font-semibold mb-2">No Topics Generated Yet</h3>
                                <p class="text-muted-foreground text-sm max-w-md mx-auto mb-8">
                                    Click "Generate Ideas" to get personalized research topics based on your level, university, and field of study.
                                </p>
                                <Button @click="generateTopics" size="lg" class="rounded-full px-8 shadow-lg shadow-primary/20 hover:shadow-primary/30 hover:scale-105 transition-all">
                                    Generate First Batch
                                </Button>
                            </div>

                            <!-- Topics Grid -->
                            <div v-else class="grid grid-cols-1 gap-6">
                                <div v-for="topic in filteredTopics" :key="topic.id" 
                                    class="group relative flex flex-col md:flex-row gap-0 md:gap-6 rounded-2xl border border-border/50 bg-card hover:bg-card/80 transition-all duration-300 hover:shadow-xl hover:shadow-primary/5 hover:border-primary/20 overflow-hidden"
                                    :class="selectedTopic === topic.title ? 'ring-1 ring-primary border-primary bg-primary/5' : ''">
                                    
                                    <!-- Left: Metrics & Actions (Desktop Sidebar / Mobile Top) -->
                                    <div class="w-full md:w-64 shrink-0 bg-muted/20 md:border-r border-border/50 p-5 flex flex-col gap-4">
                                        <div class="flex items-center justify-between md:justify-start gap-2">
                                            <Badge :variant="getDifficultyVariant(topic.difficulty)" class="rounded-md px-2 py-0.5 text-[10px] uppercase font-bold">
                                                {{ topic.difficulty }}
                                            </Badge>
                                            <span class="text-[10px] text-muted-foreground font-medium uppercase tracking-wider">{{ topic.timeline }}</span>
                                        </div>

                                        <div class="space-y-1">
                                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Feasibility</div>
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 flex-1 bg-muted rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full transition-all" 
                                                        :class="topic.feasibility_score >= 80 ? 'bg-green-500' : topic.feasibility_score >= 60 ? 'bg-yellow-500' : 'bg-red-500'"
                                                        :style="`width: ${topic.feasibility_score}%`">
                                                    </div>
                                                </div>
                                                <span class="text-xs font-bold">{{ topic.feasibility_score }}%</span>
                                            </div>
                                        </div>

                                        <div class="mt-auto pt-4 md:pt-0 grid grid-cols-2 md:grid-cols-1 gap-2">
                                             <Button @click="selectGeneratedTopic(topic)" size="sm"
                                                :variant="selectedTopic === topic.title ? 'default' : 'outline'"
                                                class="w-full h-9 transition-all"
                                                :class="selectedTopic === topic.title ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20' : 'hover:bg-primary hover:text-primary-foreground'">
                                                {{ selectedTopic === topic.title ? 'Selected' : 'Select' }}
                                                <CheckCircle v-if="selectedTopic === topic.title" class="ml-2 h-3.5 w-3.5" />
                                                <ArrowRight v-else class="ml-2 h-3.5 w-3.5" />
                                            </Button>
                                            <Button @click="openTopicInLab(topic)" size="sm" variant="ghost" class="w-full h-9 text-xs text-muted-foreground hover:text-foreground">
                                                Refine
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Right: Content -->
                                    <div class="flex-1 p-5 md:pl-0 pt-0 md:pt-5 space-y-3">
                                        <SafeHtmlText :content="topic.title" as="h3" class="text-lg font-bold leading-snug text-foreground group-hover:text-primary transition-colors" />
                                        
                                        <div class="prose prose-sm dark:prose-invert max-w-none text-muted-foreground/80 leading-relaxed line-clamp-3 group-hover:line-clamp-none transition-all duration-300">
                                            <SafeHtmlText :content="topic.description" as="div" />
                                        </div>

                                        <div class="flex flex-wrap gap-1.5 pt-3 mt-auto">
                                            <span v-for="keyword in topic.keywords.slice(0, 4)" :key="keyword"
                                                class="text-[10px] font-medium text-muted-foreground/70 bg-muted/30 px-2 py-0.5 rounded-full border border-border/30">
                                                #{{ keyword }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabsContent>
                    <!-- Import Project Tab (Coming Soon) -->
                    <TabsContent value="import" class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <Card class="border-border/50 shadow-lg shadow-primary/5 bg-card/80 backdrop-blur-sm min-h-[400px] flex items-center justify-center">
                            <div class="text-center space-y-4 max-w-md p-8">
                                <div class="bg-primary/10 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <Upload class="h-8 w-8 text-primary" />
                                </div>
                                <h3 class="text-2xl font-bold">Import Existing Project</h3>
                                <p class="text-muted-foreground leading-relaxed">
                                    We're working on a feature that will allow you to import your existing research proposals and documents directly into Finalyze.
                                </p>
                                <Badge variant="secondary" class="mt-4 text-sm px-4 py-1.5 font-medium">Coming Soon</Badge>
                            </div>
                        </Card>
                    </TabsContent>
                </Tabs>
            </main>
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
