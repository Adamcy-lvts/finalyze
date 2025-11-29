<!-- /resources/js/pages/projects/BulkGeneration.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import AppLayout from '@/layouts/AppLayout.vue'
import { useGenerationWebSocket } from '@/composables/useGenerationWebSocket'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import {
    ArrowLeft,
    AlertTriangle,
    Check,
    Clock,
    Edit,
    FileText,
    Play,
    Search,
    Sparkles,
    Terminal,
    Wifi,
    WifiOff,
    RefreshCw,
    Download,
    Eye,
    Loader2,
} from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'

interface Project {
    id: number
    slug: string
    title: string | null
    topic: string | null
    type: string
    status: string
    mode: 'auto' | 'manual'
    field_of_study: string
    university: string
    full_university_name: string
    course: string
    faculty: string | null
    chapters: any[]
    category: any
}

interface Props {
    project: Project
}

const props = defineProps<Props>()

// WebSocket composable
const {
    state,
    stages,
    activityLog,
    metadata,
    downloadLinks,
    isGenerating,
    isCompleted,
    hasFailed,
    canResume,
    estimatedTimeRemaining,
    connect,
    disconnect,
    reset,
} = useGenerationWebSocket(props.project.id)

// Local state
const isStarting = ref(false)
const showDownloadMenu = ref(false)

// Computed
const connectionStatusColor = computed(() => {
    if (state.value.isConnected) return 'text-green-500'
    if (state.value.error) return 'text-red-500'
    return 'text-yellow-500'
})

const pageTitle = computed(() => {
    if (isCompleted.value) return 'Project Ready!'
    if (isGenerating.value) return 'Generating Your Project'
    if (canResume.value) return 'Resume Generation'
    return 'Ready to Generate'
})

const progressDescription = computed(() => {
    if (isCompleted.value) {
        return 'Your project has been successfully generated. You can now download it or view the chapters.'
    }

    if (metadata.value?.current_chapter) {
        const chapterNum = metadata.value.current_chapter
        const chapterProg = metadata.value.chapter_progress || 0
        return `Generating Chapter ${chapterNum} (${chapterProg}% complete)...`
    }

    if (state.value.currentStage === 'literature_mining') {
        return 'Analyzing academic databases and collecting relevant sources...'
    }

    if (state.value.currentStage?.includes('chapter_generation')) {
        return 'Writing your chapters with proper citations and formatting...'
    }

    if (state.value.currentStage === 'html_conversion') {
        return 'Finalizing formatting and preparing your document...'
    }

    if (hasFailed.value) {
        return 'Generation failed. You can resume from where it left off.'
    }

    if (canResume.value) {
        return 'Click "Resume Generation" to continue from the last completed step.'
    }

    return 'Click the button below to start generating your complete project.'
})

const getStageIcon = (stageId: string) => {
    if (stageId === 'literature_mining') return Search
    if (stageId === 'html_conversion') return Sparkles
    return FileText
}

// Methods
const startGeneration = async (resume = false) => {
    if (isStarting.value || isGenerating.value) return

    isStarting.value = true

    if (!resume) {
        reset()
    }

    try {
        const response = await axios.post(
            route('api.projects.bulk-generate.start', props.project.slug),
            { resume }
        )

        if (response.data.generation_id) {
            toast.success(resume ? 'Resuming generation...' : 'Generation started!')
            // WebSocket will handle the rest
        } else {
            throw new Error('No generation ID returned')
        }
    } catch (error: any) {
        console.error('Failed to start generation:', error)
        toast.error(error.response?.data?.message || 'Failed to start generation')
        state.value.error = error.response?.data?.message || 'Failed to start generation'
    } finally {
        isStarting.value = false
    }
}

const cancelGeneration = async () => {
    try {
        await axios.post(route('api.projects.bulk-generate.cancel', props.project.slug))
        toast.info('Generation cancelled')
    } catch (error: any) {
        console.error('Failed to cancel generation:', error)
        toast.error('Failed to cancel generation')
    }
}

const downloadFile = async (format: 'docx' | 'pdf') => {
    const link = downloadLinks.value?.[format]
    if (link) {
        window.open(link, '_blank')
    }
}

const viewProject = () => {
    router.visit(route('projects.show', props.project.slug))
}

const goBack = () => {
    router.visit(route('projects.writing', props.project.slug))
}

const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp)
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })
}

// Check for existing generation on mount
onMounted(async () => {
    try {
        const response = await axios.get(
            route('api.projects.bulk-generate.status', props.project.slug)
        )
        
        if (['processing', 'pending'].includes(response.data.status)) {
            // Generation is in progress, WebSocket will pick it up
            state.value.status = response.data.status
            state.value.progress = response.data.progress
            state.value.currentStage = response.data.current_stage
            state.value.message = response.data.message
        } else if (response.data.status === 'completed') {
            state.value.status = 'completed'
            state.value.progress = 100
            downloadLinks.value = response.data.download_links
        } else if (response.data.status === 'failed' && response.data.progress > 0) {
            state.value.status = 'failed'
            state.value.progress = response.data.progress
        }
    } catch (error) {
        console.error('Failed to check generation status:', error)
    }
})
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="min-h-screen bg-gradient-to-b from-background to-muted/20 p-4 md:p-8">
            <div class="max-w-6xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Button variant="ghost" size="icon" @click="goBack">
                            <ArrowLeft class="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
                                {{ pageTitle }}
                            </h1>
                            <p class="text-muted-foreground mt-1">
                                {{ project.title || project.topic }}
                            </p>
                        </div>
                    </div>

                    <!-- Connection Status -->
                    <div class="flex items-center gap-2 text-sm">
                        <component
                            :is="state.isConnected ? Wifi : WifiOff"
                            :class="['h-4 w-4', connectionStatusColor]"
                        />
                        <span :class="connectionStatusColor">
                            {{ state.isConnected ? 'Connected' : 'Connecting...' }}
                        </span>
                    </div>
                </div>

                <!-- Error Alert -->
                <Alert v-if="state.error && !isGenerating" variant="destructive">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>
                        {{ state.error }}
                    </AlertDescription>
                </Alert>

                <!-- Main Content -->
                <div class="grid gap-6 lg:grid-cols-12">
                    <!-- Progress Section -->
                    <div class="lg:col-span-7 space-y-6">
                        <!-- Progress Card -->
                        <Card class="p-6">
                            <div class="space-y-4">
                                <!-- Overall Progress -->
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="font-medium">Overall Progress</span>
                                        <span class="text-muted-foreground">
                                            {{ Math.round(state.progress) }}%
                                        </span>
                                    </div>
                                    <div class="h-3 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full bg-primary transition-all duration-500 ease-out"
                                            :style="{ width: `${state.progress}%` }"
                                        />
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ progressDescription }}
                                    </p>
                                </div>

                                <!-- Time Estimate -->
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Clock class="h-4 w-4" />
                                    <span>{{ estimatedTimeRemaining }}</span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-wrap gap-3 pt-2">
                                    <Button
                                        v-if="!isGenerating && !isCompleted"
                                        @click="startGeneration(canResume)"
                                        :disabled="isStarting || !state.isConnected"
                                        size="lg"
                                    >
                                        <Loader2 v-if="isStarting" class="mr-2 h-5 w-5 animate-spin" />
                                        <Play v-else class="mr-2 h-5 w-5" />
                                        {{ canResume ? 'Resume Generation' : 'Start Generation' }}
                                    </Button>

                                    <Button
                                        v-if="isGenerating"
                                        variant="destructive"
                                        @click="cancelGeneration"
                                    >
                                        Cancel
                                    </Button>

                                    <template v-if="isCompleted">
                                        <Button @click="viewProject">
                                            <Eye class="mr-2 h-4 w-4" />
                                            View Project
                                        </Button>
                                        <Button variant="outline" @click="downloadFile('docx')">
                                            <Download class="mr-2 h-4 w-4" />
                                            Download DOCX
                                        </Button>
                                    </template>
                                </div>
                            </div>
                        </Card>

                        <!-- Stages -->
                        <div class="space-y-3">
                            <h2 class="text-lg font-semibold tracking-tight">Generation Stages</h2>

                            <div class="space-y-0">
                                <div
                                    v-for="(stage, index) in stages"
                                    :key="stage.id"
                                    class="flex gap-4"
                                >
                                    <!-- Stage Indicator -->
                                    <div class="flex flex-col items-center">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-full border-2 transition-all duration-300"
                                            :class="{
                                                'border-muted bg-background': stage.status === 'pending',
                                                'border-primary bg-primary/10 animate-pulse': stage.status === 'active',
                                                'border-green-500 bg-green-500': stage.status === 'completed',
                                                'border-red-500 bg-red-500/10': stage.status === 'error',
                                            }"
                                        >
                                            <Check
                                                v-if="stage.status === 'completed'"
                                                class="h-5 w-5 text-white"
                                            />
                                            <Loader2
                                                v-else-if="stage.status === 'active'"
                                                class="h-5 w-5 text-primary animate-spin"
                                            />
                                            <AlertTriangle
                                                v-else-if="stage.status === 'error'"
                                                class="h-5 w-5 text-red-500"
                                            />
                                            <component
                                                v-else
                                                :is="getStageIcon(stage.id)"
                                                class="h-5 w-5 text-muted-foreground"
                                            />
                                        </div>

                                        <!-- Connecting Line -->
                                        <div
                                            v-if="index < stages.length - 1"
                                            class="relative w-0.5 h-16 my-2"
                                        >
                                            <div class="absolute left-0 top-0 w-full h-full bg-muted/30" />
                                            <div
                                                class="absolute left-0 top-0 w-full bg-green-500 origin-top transition-transform duration-1000 ease-out"
                                                :class="{
                                                    'scale-y-0': stage.status === 'pending',
                                                    'scale-y-100': stage.status === 'completed',
                                                }"
                                                :style="{ height: '100%' }"
                                            />
                                        </div>
                                    </div>

                                    <!-- Stage Content -->
                                    <div class="flex-1 py-1.5 min-w-0">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <h3
                                                class="font-semibold text-base transition-colors duration-300"
                                                :class="{
                                                    'text-muted-foreground': stage.status === 'pending',
                                                    'text-foreground': stage.status === 'active',
                                                    'text-green-600 dark:text-green-400': stage.status === 'completed',
                                                    'text-red-600': stage.status === 'error',
                                                }"
                                            >
                                                {{ stage.name }}
                                            </h3>

                                            <span
                                                v-if="stage.status === 'active' && stage.chapterProgress !== undefined"
                                                class="text-xs font-semibold text-primary tabular-nums"
                                            >
                                                {{ Math.round(stage.chapterProgress) }}%
                                            </span>
                                        </div>

                                        <p
                                            class="text-sm mb-2 transition-colors duration-300"
                                            :class="{
                                                'text-muted-foreground/60': stage.status === 'pending',
                                                'text-muted-foreground': stage.status === 'active',
                                                'text-green-600/80 dark:text-green-400/80': stage.status === 'completed',
                                                'text-red-600/80': stage.status === 'error',
                                            }"
                                        >
                                            {{ stage.description }}
                                        </p>

                                        <!-- Word Count & Progress Bar for chapter stages -->
                                        <div
                                            v-if="stage.id.startsWith('chapter_generation_')"
                                            class="flex items-center gap-3"
                                        >
                                            <div
                                                v-if="(stage.wordCount ?? 0) > 0 || (stage.targetWordCount ?? 0) > 0"
                                                class="text-xs tabular-nums"
                                                :class="{
                                                    'text-muted-foreground/50': stage.status === 'pending',
                                                    'text-muted-foreground': stage.status === 'active',
                                                    'text-green-600/80': stage.status === 'completed',
                                                }"
                                            >
                                                <span :class="{ 'text-primary font-medium': stage.status === 'active' }">
                                                    {{ stage.wordCount?.toLocaleString() ?? 0 }}
                                                </span>
                                                <span class="text-muted-foreground/50"> / </span>
                                                {{ stage.targetWordCount?.toLocaleString() ?? 0 }} words
                                            </div>

                                            <!-- Progress Bar -->
                                            <div
                                                v-if="stage.status === 'active' && stage.chapterProgress !== undefined"
                                                class="flex-1"
                                            >
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/30">
                                                    <div
                                                        class="h-full bg-primary transition-all duration-500 ease-out"
                                                        :style="{ width: `${stage.chapterProgress}%` }"
                                                    />
                                                </div>
                                            </div>
                                            <div v-else-if="stage.status === 'completed'" class="flex-1">
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-green-200 dark:bg-green-900/30">
                                                    <div class="h-full bg-green-500 w-full" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Generation Time Badge -->
                                        <div
                                            v-if="stage.status === 'completed' && stage.generationTime"
                                            class="mt-1"
                                        >
                                            <Badge variant="secondary" class="text-xs">
                                                {{ stage.generationTime }}s
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Feed Section -->
                    <div class="lg:col-span-5">
                        <div class="sticky top-24">
                            <div class="mb-4 flex items-center gap-2">
                                <Terminal class="h-4 w-4 text-muted-foreground" />
                                <h2 class="text-lg font-semibold tracking-tight">System Activity</h2>
                            </div>

                            <Card class="overflow-hidden border-muted bg-card/50 shadow-sm">
                                <div class="h-[400px] overflow-y-auto p-4 font-mono text-xs scrollbar-thin">
                                    <div
                                        v-if="activityLog.length === 0"
                                        class="flex h-full flex-col items-center justify-center text-muted-foreground/50"
                                    >
                                        <Terminal class="mb-2 h-8 w-8 opacity-20" />
                                        <p>Waiting to start...</p>
                                    </div>

                                    <div v-else class="space-y-3">
                                        <div
                                            v-for="(activity, index) in activityLog"
                                            :key="index"
                                            class="group flex gap-3 transition-opacity duration-500"
                                            :class="{ 'opacity-50': index > 5 }"
                                        >
                                            <span class="shrink-0 text-muted-foreground/50 select-none">
                                                {{ formatTimestamp(activity.timestamp) }}
                                            </span>
                                            <span
                                                :class="{
                                                    'text-blue-600 dark:text-blue-400': activity.type === 'info',
                                                    'text-green-600 dark:text-green-400 font-medium':
                                                        activity.type === 'success' || activity.type === 'chapter_completed',
                                                    'text-red-600 dark:text-red-400 font-medium': activity.type === 'error',
                                                    'text-purple-600 dark:text-purple-400 font-bold': activity.type === 'stage',
                                                    'text-cyan-600 dark:text-cyan-400': activity.type === 'chapter_progress',
                                                    'text-amber-600 dark:text-amber-400': activity.type === 'mining',
                                                    'text-pink-600 dark:text-pink-400': activity.type === 'conversion',
                                                }"
                                            >
                                                {{ activity.message }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 20px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.5);
}
</style>
