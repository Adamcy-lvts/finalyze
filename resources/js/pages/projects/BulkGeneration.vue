<!-- /resources/js/pages/projects/BulkGeneration.vue -->
<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import AppLayout from '@/layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import {
    ArrowLeft,
    BookOpen,
    Brain,
    CheckCircle,
    Circle,
    Clock,
    Download,
    FileText,
    Loader2,
    Play,
    Search,
    Sparkles,
    Target,
    Zap
} from 'lucide-vue-next'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'

interface Project {
    id: number
    slug: string
    title: string
    topic: string
    type: string
    status: string
    mode: 'auto' | 'manual'
    field_of_study: string
    university: string
    course: string
    faculty: string
    chapters: any[]
    category: any
}

interface Props {
    project: Project
}

const props = defineProps<Props>()

// Generation state
const isGenerating = ref(false)
const isCompleted = ref(false)
const hasError = ref(false)
const errorMessage = ref('')
const overallProgress = ref(0)
const currentStage = ref('')
const currentMessage = ref('')
const downloadLinks = ref<any>(null)

// Stage definitions
const stages = ref([
    {
        id: 'literature_mining',
        name: 'Literature Mining',
        description: 'Collecting relevant academic papers and sources',
        icon: Search,
        progress: 0,
        status: 'pending', // pending, active, completed, error
        details: [] as string[],
        range: [0, 20]
    },
    {
        id: 'chapter_generation',
        name: 'Chapter Generation',
        description: 'Generating chapters with real citations and references',
        icon: FileText,
        progress: 0,
        status: 'pending',
        details: [] as string[],
        range: [20, 70]
    },
    {
        id: 'preliminary_pages',
        name: 'Preliminary Pages',
        description: 'Creating title page, abstract, and table of contents',
        icon: BookOpen,
        progress: 0,
        status: 'pending',
        details: [] as string[],
        range: [70, 85]
    },
    {
        id: 'appendices',
        name: 'Appendices & Supplements',
        description: 'Generating appendices and supplementary materials',
        icon: Target,
        progress: 0,
        status: 'pending',
        details: [] as string[],
        range: [85, 95]
    },
    {
        id: 'document_assembly',
        name: 'Document Assembly',
        description: 'Combining all parts into final document',
        icon: Circle,
        progress: 0,
        status: 'pending',
        details: [] as string[],
        range: [95, 99]
    },
    {
        id: 'defense_prep',
        name: 'Defense Preparation',
        description: 'Preparing defense questions and summaries',
        icon: Brain,
        progress: 0,
        status: 'pending',
        details: [] as string[],
        range: [99, 100]
    }
])

// Activity feed for live updates
const activityFeed = ref<Array<{
    timestamp: Date
    message: string
    type: 'info' | 'success' | 'error' | 'stage'
}>>([])

// Server-Sent Events connection
let eventSource: EventSource | null = null

// Computed properties
const activeStageIndex = computed(() => {
    return stages.value.findIndex(stage => stage.status === 'active')
})

const completedStagesCount = computed(() => {
    return stages.value.filter(stage => stage.status === 'completed').length
})

const estimatedTime = computed(() => {
    if (isCompleted.value) return 'Completed'
    if (hasError.value) return 'Error occurred'
    if (!isGenerating.value) return '15-20 minutes'

    // Rough time estimation based on progress
    const remaining = 100 - overallProgress.value
    const timePerPercent = 12 // seconds
    const remainingSeconds = remaining * timePerPercent

    if (remainingSeconds < 60) return `~${Math.ceil(remainingSeconds)} seconds`
    const remainingMinutes = Math.ceil(remainingSeconds / 60)
    return `~${remainingMinutes} minutes`
})

/**
 * Start the bulk generation process
 */
const startGeneration = () => {
    if (isGenerating.value) return

    isGenerating.value = true
    hasError.value = false
    errorMessage.value = ''
    overallProgress.value = 0
    currentStage.value = ''
    currentMessage.value = ''
    downloadLinks.value = null
    isCompleted.value = false

    // Reset all stages
    stages.value.forEach(stage => {
        stage.progress = 0
        stage.status = 'pending'
        stage.details = []
    })

    // Clear activity feed
    activityFeed.value = []

    addToActivityFeed('Starting comprehensive project generation...', 'info')

    // Start SSE connection
    connectToGenerationStream()
}

/**
 * Connect to Server-Sent Events stream for real-time updates
 */
const connectToGenerationStream = () => {
    if (eventSource) {
        eventSource.close()
    }

    eventSource = new EventSource(`/api/projects/${props.project.slug}/bulk-generate/stream`)

    eventSource.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data)
            handleStreamEvent(data)
        } catch (error) {
            console.error('Failed to parse SSE data:', error)
        }
    }

    eventSource.onerror = (error) => {
        console.error('SSE connection error:', error)
        handleGenerationError('Connection lost. Please try again.')
        eventSource?.close()
        eventSource = null
    }
}

/**
 * Handle incoming stream events
 */
const handleStreamEvent = (data: any) => {
    switch (data.type) {
        case 'start':
            currentMessage.value = data.message
            addToActivityFeed(data.message, 'info')
            break

        case 'stage_start':
            handleStageStart(data)
            break

        case 'stage_complete':
            handleStageComplete(data)
            break

        case 'progress':
            handleProgressUpdate(data)
            break

        case 'stage_error':
            handleStageError(data)
            break

        case 'complete':
            handleGenerationComplete(data)
            break

        case 'error':
            handleGenerationError(data.message)
            break

        default:
            console.log('Unknown event type:', data.type)
    }
}

/**
 * Handle stage start events
 */
const handleStageStart = (data: any) => {
    const stage = stages.value.find(s => s.id === data.stage)
    if (stage) {
        stage.status = 'active'
        stage.progress = 0
        currentStage.value = stage.name
        currentMessage.value = data.message
        overallProgress.value = data.progress || 0

        addToActivityFeed(`Started: ${stage.name}`, 'stage')
    }
}

/**
 * Handle stage completion events
 */
const handleStageComplete = (data: any) => {
    const stage = stages.value.find(s => s.id === data.stage)
    if (stage) {
        stage.status = 'completed'
        stage.progress = 100
        overallProgress.value = data.progress || 0

        // Add detailed results if available
        if (data.details && Array.isArray(data.details)) {
            stage.details = [...stage.details, ...data.details]
        }

        // Special handling for literature mining completion
        if (data.stage === 'literature_mining' && data.papers_collected) {
            addToActivityFeed(`🎉 Literature mining complete! Collected ${data.papers_collected} high-quality papers`, 'success')
        } else {
            addToActivityFeed(data.message, 'success')
        }
    }
}

/**
 * Handle stage errors
 */
const handleStageError = (data: any) => {
    const stage = stages.value.find(s => s.id === data.stage)
    if (stage) {
        stage.status = 'error'
        stage.progress = 0
    }

    overallProgress.value = data.progress || 0
    addToActivityFeed(`❌ Stage error: ${data.message}`, 'error')
}

/**
 * Handle progress updates within stages
 */
const handleProgressUpdate = (data: any) => {
    const stage = stages.value.find(s => s.id === data.stage)
    if (stage) {
        // Calculate stage-specific progress based on stage range
        const stageRange = stage.range[1] - stage.range[0]
        const stageProgress = ((data.progress - stage.range[0]) / stageRange) * 100
        stage.progress = Math.max(0, Math.min(100, stageProgress))

        if (data.detail) {
            stage.details.push(data.detail)
            // Keep only last 5 details to avoid overflow
            if (stage.details.length > 5) {
                stage.details = stage.details.slice(-5)
            }
        }
    }

    overallProgress.value = data.progress || 0
    currentMessage.value = data.message

    // Add progress updates to activity feed
    if (data.detail) {
        addToActivityFeed(data.detail, 'info')
    }
}

/**
 * Handle generation completion
 */
const handleGenerationComplete = (data: any) => {
    isGenerating.value = false
    isCompleted.value = true
    overallProgress.value = 100
    currentMessage.value = data.message
    downloadLinks.value = data.download_links

    // Mark all stages as completed
    stages.value.forEach(stage => {
        stage.status = 'completed'
        stage.progress = 100
    })

    addToActivityFeed('🎉 Project generation completed!', 'success')

    toast('Success!', {
        description: 'Your complete project has been generated successfully!',
        duration: 5000,
    })

    // Close SSE connection
    if (eventSource) {
        eventSource.close()
        eventSource = null
    }
}

/**
 * Handle generation errors
 */
const handleGenerationError = (message: string) => {
    isGenerating.value = false
    hasError.value = true
    errorMessage.value = message

    // Mark current active stage as error
    const activeStage = stages.value.find(s => s.status === 'active')
    if (activeStage) {
        activeStage.status = 'error'
    }

    addToActivityFeed(`Error: ${message}`, 'error')

    toast('Generation Failed', {
        description: message,
        duration: 10000,
    })

    // Close SSE connection
    if (eventSource) {
        eventSource.close()
        eventSource = null
    }
}

/**
 * Add message to activity feed
 */
const addToActivityFeed = (message: string, type: 'info' | 'success' | 'error' | 'stage') => {
    activityFeed.value.unshift({
        timestamp: new Date(),
        message,
        type
    })

    // Keep only last 50 items
    if (activityFeed.value.length > 50) {
        activityFeed.value = activityFeed.value.slice(0, 50)
    }

    // Scroll to top of activity feed
    nextTick(() => {
        const feedElement = document.getElementById('activity-feed')
        if (feedElement) {
            feedElement.scrollTop = 0
        }
    })
}

/**
 * Cancel generation
 */
const cancelGeneration = () => {
    if (!isGenerating.value) return

    if (confirm('Are you sure you want to cancel the generation process?')) {
        if (eventSource) {
            eventSource.close()
            eventSource = null
        }

        isGenerating.value = false
        addToActivityFeed('Generation cancelled by user', 'error')

        toast('Cancelled', {
            description: 'Generation process has been cancelled',
        })
    }
}

/**
 * Go back to writing page
 */
const goBack = () => {
    if (isGenerating.value) {
        if (!confirm('Generation is in progress. Are you sure you want to leave?')) {
            return
        }
        cancelGeneration()
    }

    router.visit(route('projects.writing', props.project.slug))
}

/**
 * Get stage status icon
 */
const getStageIcon = (stage: any) => {
    switch (stage.status) {
        case 'completed':
            return CheckCircle
        case 'active':
            return Loader2
        case 'error':
            return Circle // You might want to use an error icon
        default:
            return Circle
    }
}

/**
 * Get stage status color classes
 */
const getStageStatusClasses = (stage: any) => {
    switch (stage.status) {
        case 'completed':
            return 'text-green-600 bg-green-50 border-green-200'
        case 'active':
            return 'text-blue-600 bg-blue-50 border-blue-200'
        case 'error':
            return 'text-red-600 bg-red-50 border-red-200'
        default:
            return 'text-gray-400 bg-gray-50 border-gray-200'
    }
}

/**
 * Format timestamp for activity feed
 */
const formatTimestamp = (date: Date) => {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

// Lifecycle
onMounted(() => {
    // Auto-start generation if URL has ?start=true
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.get('start') === 'true') {
        setTimeout(startGeneration, 1000)
    }
})

onUnmounted(() => {
    if (eventSource) {
        eventSource.close()
        eventSource = null
    }
})
</script>

<template>
    <AppLayout :title="`Bulk Generation: ${project.title}`">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button @click="goBack" variant="ghost" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Writing
                    </Button>
                    <div class="h-6 border-l border-gray-300" />
                    <div>
                        <h1 class="text-2xl font-bold">Complete Project Generation</h1>
                        <p class="text-sm text-muted-foreground">{{ project.title }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Badge variant="outline">{{ project.type }}</Badge>
                    <Badge variant="outline">{{ estimatedTime }}</Badge>
                </div>
            </div>

            <!-- Overall Progress -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Sparkles class="h-5 w-5 text-purple-600" />
                                Overall Progress
                            </CardTitle>
                            <CardDescription>
                                {{ isCompleted ? 'Generation completed!' : currentMessage || 'Ready to start comprehensive project generation' }}
                            </CardDescription>
                        </div>

                        <div class="text-right">
                            <div class="text-2xl font-bold text-purple-600">{{ Math.round(overallProgress) }}%</div>
                            <div class="text-xs text-muted-foreground">
                                {{ completedStagesCount }}/{{ stages.length }} stages
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <Progress :model-value="overallProgress" class="h-3" />

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <template v-if="!isGenerating && !isCompleted && !hasError">
                                <Button @click="startGeneration" size="lg" class="bg-gradient-to-r from-purple-600 to-blue-600">
                                    <Play class="mr-2 h-5 w-5" />
                                    Start Generation
                                </Button>
                            </template>

                            <template v-else-if="isGenerating">
                                <Button @click="cancelGeneration" variant="outline" size="lg">
                                    Cancel Generation
                                </Button>
                            </template>

                            <template v-else-if="isCompleted && downloadLinks">
                                <Button v-if="downloadLinks.word" as="a" :href="downloadLinks.word" size="lg" class="bg-green-600 hover:bg-green-700">
                                    <Download class="mr-2 h-5 w-5" />
                                    Download Word Document
                                </Button>
                            </template>
                        </div>

                        <div v-if="currentStage" class="text-sm text-muted-foreground">
                            Current: {{ currentStage }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Error Alert -->
            <Alert v-if="hasError" class="border-red-200 bg-red-50">
                <AlertDescription class="text-red-800">
                    {{ errorMessage }}
                </AlertDescription>
            </Alert>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Generation Stages -->
                <div class="lg:col-span-2 space-y-4">
                    <h2 class="text-lg font-semibold">Generation Stages</h2>

                    <div class="space-y-3">
                        <Card
                            v-for="(stage, index) in stages"
                            :key="stage.id"
                            class="transition-all duration-200"
                            :class="{
                                'ring-2 ring-blue-200 bg-blue-50/30': stage.status === 'active',
                                'bg-green-50/30': stage.status === 'completed',
                                'bg-red-50/30': stage.status === 'error'
                            }"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2"
                                             :class="getStageStatusClasses(stage)">
                                            <component
                                                :is="getStageIcon(stage)"
                                                class="h-5 w-5"
                                                :class="{ 'animate-spin': stage.status === 'active' }"
                                            />
                                        </div>

                                        <div class="flex-1">
                                            <CardTitle class="text-base">{{ stage.name }}</CardTitle>
                                            <CardDescription class="text-sm">{{ stage.description }}</CardDescription>

                                            <!-- Stage progress -->
                                            <div v-if="stage.status === 'active' || stage.status === 'completed'" class="mt-2">
                                                <Progress :model-value="stage.progress" class="h-1.5" />
                                            </div>
                                        </div>
                                    </div>

                                    <Badge
                                        variant="outline"
                                        class="text-xs"
                                        :class="{
                                            'bg-blue-100 text-blue-800': stage.status === 'active',
                                            'bg-green-100 text-green-800': stage.status === 'completed',
                                            'bg-red-100 text-red-800': stage.status === 'error',
                                            'bg-gray-100 text-gray-600': stage.status === 'pending'
                                        }"
                                    >
                                        {{
                                            stage.status === 'pending' ? 'Waiting' :
                                            stage.status === 'active' ? 'In Progress' :
                                            stage.status === 'completed' ? 'Completed' :
                                            'Error'
                                        }}
                                    </Badge>
                                </div>
                            </CardHeader>

                            <!-- Stage details -->
                            <CardContent v-if="stage.details.length > 0" class="pt-0">
                                <div class="space-y-1">
                                    <div
                                        v-for="detail in stage.details.slice(-3)"
                                        :key="detail"
                                        class="text-xs text-muted-foreground pl-13"
                                    >
                                        {{ detail }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Activity Feed -->
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold">Live Activity</h2>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                Real-time Updates
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                id="activity-feed"
                                class="max-h-96 overflow-y-auto space-y-2 p-4"
                            >
                                <div
                                    v-for="(activity, index) in activityFeed"
                                    :key="index"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <div class="text-xs text-muted-foreground pt-0.5 w-16 flex-shrink-0">
                                        {{ formatTimestamp(activity.timestamp) }}
                                    </div>
                                    <div
                                        class="flex-1"
                                        :class="{
                                            'text-blue-700': activity.type === 'info',
                                            'text-green-700': activity.type === 'success',
                                            'text-red-700': activity.type === 'error',
                                            'font-medium text-purple-700': activity.type === 'stage'
                                        }"
                                    >
                                        {{ activity.message }}
                                    </div>
                                </div>

                                <div v-if="activityFeed.length === 0" class="text-center text-muted-foreground py-8">
                                    No activity yet. Click "Start Generation" to begin.
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Project Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Project Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div>
                                <span class="font-medium">Field:</span>
                                <span class="ml-2 text-muted-foreground">{{ project.field_of_study }}</span>
                            </div>
                            <div>
                                <span class="font-medium">University:</span>
                                <span class="ml-2 text-muted-foreground">{{ project.university }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Faculty:</span>
                                <span class="ml-2 text-muted-foreground">{{ project.faculty }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Type:</span>
                                <span class="ml-2 text-muted-foreground">{{ project.type }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>