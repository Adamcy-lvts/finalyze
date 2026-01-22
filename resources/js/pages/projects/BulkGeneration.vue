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
import SafeHtmlText from '@/components/SafeHtmlText.vue'
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
    Eye,
    Loader2,
} from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'
import PurchaseModal from '@/components/PurchaseModal.vue'
import { useWordBalance } from '@/composables/useWordBalance'

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
    restoreFromApiData,
    reset,
} = useGenerationWebSocket(props.project.id, props.project.slug)

// Word balance guard
const {
    showPurchaseModal,
    requiredWordsForModal,
    actionDescriptionForModal,
    checkAndPrompt,
    closePurchaseModal,
    estimates,
} = useWordBalance()

// Local state
const isStarting = ref(false)
const isDownloadingPdf = ref(false)

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

    const remainingChapters = props.project.chapters?.filter(
        (c: any) => c.status !== 'completed' && (c.word_count ?? 0) <= 0
    ).length || props.project.chapters?.length || 1

    const avgTarget = (() => {
        const targets = (props.project.chapters || [])
            .map((c: any) => c.target_word_count)
            .filter((t: any) => typeof t === 'number' && t > 0)

        if (targets.length > 0) {
            return Math.ceil(targets.reduce((sum: number, val: number) => sum + val, 0) / targets.length)
        }

        return 2000 // conservative default
    })()

    const requiredWords = estimates.chapter(avgTarget) * remainingChapters
    const actionLabel = resume ? 'resume bulk generation' : 'bulk generate chapters'

    if (!checkAndPrompt(requiredWords, actionLabel)) {
        return
    }

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

const downloadProjectPdf = async () => {
    if (isDownloadingPdf.value) return

    const pdfUrl = downloadLinks.value?.pdf || route('export.project.pdf', { project: props.project.slug })
    const projectName = props.project.title || props.project.topic || 'Project'

    isDownloadingPdf.value = true
    toast.loading('Generating project PDF...', { id: 'download-project-pdf' })

    try {
        const response = await fetch(pdfUrl, {
            method: 'GET',
            headers: {
                Accept: 'application/pdf',
            },
        })

        if (response.ok) {
            const contentType = response.headers.get('content-type')
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json()
                toast.error('Export Failed', {
                    id: 'download-project-pdf',
                    description: errorData.message || 'An error occurred during PDF export.',
                })
                return
            }

            const blob = await response.blob()
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = `${props.project.slug}_full_project.pdf`
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            window.URL.revokeObjectURL(url)

            toast.success('Project PDF exported successfully!', {
                id: 'download-project-pdf',
                description: `${projectName} has been downloaded as PDF.`,
            })
        } else {
            try {
                const errorData = await response.json()
                toast.error('Export Failed', {
                    id: 'download-project-pdf',
                    description: errorData.message || 'An error occurred during PDF export.',
                })
            } catch (error) {
                toast.error('Export Failed', {
                    id: 'download-project-pdf',
                    description: 'Unable to export project as PDF. Please try again.',
                })
            }
        }
    } catch (error) {
        toast.error('Export Failed', {
            id: 'download-project-pdf',
            description: 'Network error occurred. Please check your connection and try again.',
        })
    } finally {
        isDownloadingPdf.value = false
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

        if (response.data.status !== 'not_started') {
            restoreFromApiData(response.data)
        }
    } catch (error) {
        console.error('Failed to check generation status:', error)
    }
})
</script>

<template>
    <AppLayout :title="pageTitle">
        <!-- Background Effects -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div
                class="absolute top-[-10%] right-[-5%] w-[500px] h-[500px] bg-primary/10 rounded-full blur-[100px] mix-blend-screen animate-pulse duration-3000" />
            <div
                class="absolute bottom-[-10%] left-[-5%] w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[120px] mix-blend-screen" />
        </div>

        <div class="relative min-h-screen p-4 md:p-8 lg:p-12">
            <div class="max-w-5xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex flex-col gap-6">
                    <!-- Top Bar with Back Button and Status -->
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <Button variant="outline" size="icon" @click="goBack"
                            class="rounded-xl border-dashed border-gray-600/30 hover:bg-gray-100/10 hover:border-primary/50 transition-all shrink-0">
                            <ArrowLeft class="h-5 w-5" />
                        </Button>

                        <!-- Connection Status Badge -->
                        <div
                            class="flex items-center gap-3 px-4 py-2 rounded-full border border-border/50 bg-background/50 backdrop-blur-md shadow-sm self-end md:self-auto">
                            <div class="relative flex h-3 w-3">
                                <span v-if="state.isConnected"
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3"
                                    :class="state.isConnected ? 'bg-green-500' : 'bg-red-500'"></span>
                            </div>
                            <span class="text-sm font-medium" :class="connectionStatusColor">
                                {{ state.isConnected ? 'System Online' : 'Connecting...' }}
                            </span>
                        </div>
                    </div>

                    <!-- Main Title Section -->
                    <div class="space-y-2">
                        <SafeHtmlText as="h1"
                            class="text-2xl md:text-3xl font-bold tracking-tight bg-gradient-to-r from-foreground to-foreground/60 bg-clip-text text-transparent leading-tight"
                            :content="project.title || project.topic" />
                        <p class="text-xl text-muted-foreground font-light tracking-wide">
                            {{ pageTitle }}
                        </p>
                    </div>
                </div>

                <!-- Error Alert -->
                <transition enter-active-class="transition duration-300 ease-out"
                    enter-from-class="transform -translate-y-2 opacity-0"
                    enter-to-class="transform translate-y-0 opacity-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="transform translate-y-0 opacity-100"
                    leave-to-class="transform -translate-y-2 opacity-0">
                    <Alert v-if="state.error && !isGenerating" variant="destructive"
                        class="border-red-500/50 bg-red-500/10">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>Execution Error</AlertTitle>
                        <AlertDescription>
                            {{ state.error }}
                        </AlertDescription>
                    </Alert>
                </transition>

                <!-- Main Grid -->
                <div class="grid lg:grid-cols-12 gap-8 items-start relative">

                    <!-- Left Column: Generation Sequence -->
                    <div class="lg:col-span-7 space-y-8 order-2 lg:order-1">
                        <!-- Stages Timeline -->
                        <div class="space-y-6 pl-2 z-10 relative">
                            <h2 class="text-xl font-semibold tracking-tight">Generation Sequence</h2>

                            <div class="relative space-y-0">
                                <!-- Continuous Line Background -->
                                <div
                                    class="absolute left-[19px] top-6 bottom-6 w-0.5 bg-gradient-to-b from-border/50 via-border/30 to-transparent">
                                </div>

                                <div v-for="(stage, index) in stages" :key="stage.id" class="relative flex gap-4 group">
                                    <!-- Stage Icon/Indicator -->
                                    <div class="flex flex-col items-center">
                                        <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 shadow-sm transition-all duration-500"
                                            :class="{
                                                'border-muted bg-card scale-95 opacity-70': stage.status === 'pending',
                                                'border-primary bg-background ring-2 ring-primary/20 scale-110 shadow-[0_0_10px_rgba(var(--primary),0.3)]': stage.status === 'active',
                                                'border-green-500 bg-green-500 text-white scale-100 shadow-green-500/20': stage.status === 'completed',
                                                'border-red-500 bg-red-500/10 text-red-500': stage.status === 'error',
                                            }">
                                            <Check v-if="stage.status === 'completed'" class="h-4 w-4 stroke-[3]" />
                                            <Loader2 v-else-if="stage.status === 'active'"
                                                class="h-4 w-4 animate-spin text-primary" />
                                            <AlertTriangle v-else-if="stage.status === 'error'" class="h-4 w-4" />
                                            <component v-else :is="getStageIcon(stage.id)"
                                                class="h-3.5 w-3.5 text-muted-foreground" />
                                        </div>

                                        <!-- Active Line Segment -->
                                        <div v-if="index < stages.length - 1"
                                            class="w-0.5 flex-1 transition-all duration-700 ease-in-out mt-[-1px] mb-[-1px]"
                                            :class="{
                                                'bg-gradient-to-b from-green-500 to-transparent': stage.status === 'completed',
                                                'bg-transparent': stage.status !== 'completed'
                                            }">
                                            <div v-if="stage.status === 'active'"
                                                class="w-full h-full bg-gradient-to-b from-primary to-transparent animate-pulse shadow-[0_0_10px_rgba(var(--primary),0.5)]">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 pb-6 min-w-0 transition-opacity duration-300"
                                        :class="{ 'opacity-50 hover:opacity-100': stage.status === 'pending' }">
                                        <div
                                            class="flex items-center justify-between gap-4 p-4 rounded-xl border border-border/10 bg-card/10 backdrop-blur-md hover:border-primary/20 hover:bg-card/30 hover:shadow-[0_0_20px_rgba(var(--primary),0.1)] transition-all duration-300 group-hover:bg-card/20">
                                            <div class="space-y-1 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <h3 class="font-semibold text-base tracking-tight" :class="{
                                                        'text-foreground': stage.status === 'active' || stage.status === 'completed',
                                                        'text-muted-foreground': stage.status === 'pending',
                                                        'text-red-500': stage.status === 'error',
                                                    }">
                                                        {{ stage.name }}
                                                    </h3>
                                                    <Badge v-if="stage.status === 'completed' && stage.generationTime"
                                                        variant="secondary"
                                                        class="text-[10px] h-5 px-1.5 font-mono bg-secondary/50 text-secondary-foreground/70">
                                                        {{ stage.generationTime }}s
                                                    </Badge>
                                                </div>

                                                <p class="text-sm text-balance leading-relaxed" :class="{
                                                    'text-muted-foreground/80': stage.status !== 'active',
                                                    'text-primary/90 font-medium': stage.status === 'active'
                                                }">
                                                    {{ stage.description }}
                                                </p>

                                                <!-- Chapter specific stats -->
                                                <div v-if="stage.id.startsWith('chapter_generation_') && (stage.status === 'active' || stage.status === 'completed')"
                                                    class="mt-3 bg-secondary/30 rounded-lg p-3 space-y-2.5 border border-border/30 shadow-inner">
                                                    <div
                                                        class="flex justify-between text-xs font-medium text-muted-foreground">
                                                        <span
                                                            class="uppercase tracking-wider text-[10px]">Progress</span>
                                                        <span class="text-xs tracking-tight text-muted-foreground">
                                                            {{ stage.description || stage.name }}
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="h-2 w-full overflow-hidden rounded-full bg-background/50 border border-white/5">
                                                        <div class="h-full transition-all duration-700 ease-out relative overflow-hidden"
                                                            :class="{
                                                                'bg-gradient-to-r from-green-500 to-emerald-400': stage.status === 'completed',
                                                                'bg-gradient-to-r from-blue-500 to-indigo-500': stage.status === 'active'
                                                            }"
                                                            :style="{ width: stage.status === 'completed' ? '100%' : `${stage.chapterProgress}%` }">
                                                            <!-- Shimmer Effect for Active State -->
                                                            <div v-if="stage.status === 'active'"
                                                                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent skew-x-[-20deg] animate-shimmer"
                                                                style="background-size: 200% 100%;"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Simple active indicator arrow -->
                                            <div v-if="stage.status === 'active'" class="text-primary hidden sm:block">
                                                <Loader2 class="h-4 w-4 animate-spin" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Control & Logs (Sticky) -->
                    <div
                        class="lg:col-span-5 flex flex-col gap-6 lg:sticky lg:top-24 lg:h-[calc(100vh-6rem)] order-1 lg:order-2">
                        <!-- Main Control Card -->
                        <div class="group relative z-20">
                            <!-- Glow Effect -->
                            <div
                                class="absolute -inset-0.5 bg-gradient-to-r from-primary/30 to-purple-500/30 rounded-2xl blur opacity-30 group-hover:opacity-50 transition duration-1000">
                            </div>

                            <Card
                                class="relative p-6 md:p-8 border-border/50 bg-card/80 backdrop-blur-xl transition-all duration-300 shadow-2xl">
                                <div class="space-y-6 md:space-y-8">
                                    <!-- Circular Progress Ring & Text -->
                                    <div class="flex flex-col items-center justify-center py-6">
                                        <div class="relative w-48 h-48">
                                            <!-- SVG Circle -->
                                            <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                                                <!-- Background Circle -->
                                                <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor"
                                                    class="text-secondary/30" stroke-width="8" />
                                                <!-- Progress Circle -->
                                                <circle cx="50" cy="50" r="45" fill="none"
                                                    stroke="url(#progress-gradient)" stroke-width="8"
                                                    stroke-linecap="round" class="transition-all duration-1000 ease-out"
                                                    :stroke-dasharray="2 * Math.PI * 45"
                                                    :stroke-dashoffset="2 * Math.PI * 45 * (1 - state.progress / 100)" />
                                                <!-- Gradient Definition -->
                                                <defs>
                                                    <linearGradient id="progress-gradient" x1="0%" y1="0%" x2="100%"
                                                        y2="0%">
                                                        <stop offset="0%" stop-color="#3b82f6" />
                                                        <stop offset="50%" stop-color="#6366f1" />
                                                        <stop offset="100%" stop-color="#a855f7" />
                                                    </linearGradient>
                                                </defs>
                                            </svg>

                                            <!-- Centered Text -->
                                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                                <span class="text-4xl font-bold tabular-nums tracking-tighter">{{
                                                    Math.round(state.progress) }}%</span>
                                                <span
                                                    class="text-xs font-medium uppercase tracking-widest text-muted-foreground/80 mt-1">
                                                    {{ isCompleted ? 'Complete' : 'Progress' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-6 text-center space-y-2">
                                            <p
                                                class="text-base text-muted-foreground leading-relaxed animate-pulse-slow">
                                                {{ progressDescription }}
                                            </p>
                                            <div
                                                class="flex items-center justify-center gap-2 text-sm text-muted-foreground bg-secondary/30 px-3 py-1 rounded-full w-fit mx-auto">
                                                <Clock class="h-4 w-4 text-primary" />
                                                <span class="font-mono">{{ estimatedTimeRemaining }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-col gap-4 pt-4 border-t border-border/50">
                                        <Button v-if="!isGenerating && !isCompleted" @click="startGeneration(canResume)"
                                            :disabled="isStarting || !state.isConnected" size="lg"
                                            class="w-full rounded-xl shadow-lg shadow-primary/20 transition-all hover:scale-[1.02] active:scale-[0.98] text-base font-semibold py-6">
                                            <Loader2 v-if="isStarting" class="mr-2 h-5 w-5 animate-spin" />
                                            <Play v-else class="mr-2 h-5 w-5 fill-current" />
                                            {{ canResume ? 'Resume Operation' : 'Initialize Generation' }}
                                        </Button>

                                        <Button v-if="isGenerating" variant="destructive" size="lg"
                                            @click="cancelGeneration"
                                            class="w-full rounded-xl shadow-lg shadow-destructive/20 hover:bg-destructive/90 py-6">
                                            Cancel Process
                                        </Button>

                                        <template v-if="isCompleted">
                                            <div class="grid grid-cols-2 gap-3">
                                                <Button @click="viewProject" size="lg"
                                                    class="rounded-xl shadow-lg shadow-primary/20 hover:scale-[1.02] transition-all text-base py-6">
                                                    <Edit class="mr-2 h-5 w-5" />
                                                    View in Editor
                                                </Button>
                                                <Button variant="secondary" size="lg" @click="downloadProjectPdf"
                                                    :disabled="isDownloadingPdf"
                                                    class="rounded-xl hover:bg-secondary/80 py-6">
                                                    <Loader2 v-if="isDownloadingPdf"
                                                        class="mr-2 h-4 w-4 animate-spin" />
                                                    <FileText v-else class="mr-2 h-4 w-4" />
                                                    Export PDF
                                                </Button>
                                            </div>

                                            <!-- AI Disclaimer -->
                                            <Alert variant="default"
                                                class="bg-yellow-500/10 border-yellow-500/20 text-yellow-500">
                                                <AlertTriangle class="h-4 w-4" />
                                                <AlertDescription class="text-xs leading-relaxed">
                                                    AI is not perfect and can make mistakes. Please review each chapter
                                                    and make adjustments where necessary. This will also help you know
                                                    the project well enough to defend it.
                                                </AlertDescription>
                                            </Alert>
                                        </template>
                                    </div>
                                </div>
                            </Card>
                        </div>

                        <!-- System Logs (Flex container to fill remaining height) -->
                        <div class="hidden lg:flex flex-col flex-1 min-h-0 relative">
                            <!-- Header -->
                            <div class="flex items-center justify-between px-1 bg-background pb-2">
                                <div class="flex items-center gap-2">
                                    <Terminal class="h-5 w-5 text-muted-foreground" />
                                    <h2 class="text-lg font-semibold tracking-tight">System Logs</h2>
                                </div>
                                <div class="flex gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                                </div>
                            </div>

                            <Card
                                class="flex-1 overflow-hidden bg-[#0c0c0c] border-gray-800 shadow-2xl rounded-xl flex flex-col">
                                <!-- Terminal Header -->
                                <div class="bg-gray-900/50 border-b border-gray-800 p-2 flex justify-center shrink-0">
                                    <span
                                        class="text-[10px] uppercase font-mono tracking-widest text-gray-500">generation-task.sh</span>
                                </div>

                                <!-- Terminal Content -->
                                <div
                                    class="flex-1 overflow-y-auto p-4 font-mono text-xs md:text-sm scrollbar-hide relative">

                                    <transition-group name="list" tag="div" class="space-y-2">
                                        <div v-if="activityLog.length === 0" key="empty"
                                            class="absolute inset-0 flex flex-col items-center justify-center text-gray-700 select-none">
                                            <div class="animate-pulse flex flex-col items-center gap-2">
                                                <Terminal class="h-10 w-10 opacity-20" />
                                                <p class="font-sans text-sm">Waiting for initialization...</p>
                                            </div>
                                        </div>

                                        <div v-for="(activity, index) in activityLog" :key="index"
                                            class="flex gap-3 items-start border-l-2 pl-3 py-0.5 transition-all hover:bg-white/5"
                                            :class="{
                                                'border-blue-500/50': activity.type === 'info',
                                                'border-green-500/50': activity.type === 'success' || activity.type === 'chapter_completed',
                                                'border-red-500/50': activity.type === 'error',
                                                'border-purple-500/50': activity.type === 'stage',
                                                'border-transparent': !['info', 'success', 'chapter_completed', 'error', 'stage'].includes(activity.type)
                                            }">
                                            <span class="shrink-0 text-gray-600 select-none text-[10px] mt-[3px]">
                                                {{ formatTimestamp(activity.timestamp) }}
                                            </span>
                                            <span class="break-words leading-tight" :class="{
                                                'text-blue-400': activity.type === 'info',
                                                'text-green-400 font-medium':
                                                    activity.type === 'success' || activity.type === 'chapter_completed',
                                                'text-red-400 font-bold bg-red-900/10 px-1 rounded': activity.type === 'error',
                                                'text-purple-400 font-bold': activity.type === 'stage',
                                                'text-cyan-400': activity.type === 'chapter_progress',
                                                'text-amber-400': activity.type === 'mining',
                                                'text-pink-400': activity.type === 'conversion',
                                                'text-gray-300': !activity.type
                                            }">
                                                <span v-if="activity.type === 'stage'"
                                                    class="text-gray-500 mr-2">$</span>
                                                {{ activity.message }}
                                            </span>
                                        </div>

                                        <!-- Blinking cursor at the end -->
                                        <div key="cursor" class="h-4 w-2 bg-gray-500 animate-pulse mt-2 inline-block">
                                        </div>
                                    </transition-group>
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <PurchaseModal :open="showPurchaseModal" :current-balance="0" :required-words="requiredWordsForModal"
            :action-description="actionDescriptionForModal" @update:open="(v) => (showPurchaseModal = v)"
            @close="closePurchaseModal" />
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

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

/* List Transitions */
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}

@keyframes shimmer {
    0% {
        transform: translateX(-150%) skewX(-12deg);
    }

    100% {
        transform: translateX(150%) skewX(-12deg);
    }
}

.animate-shimmer {
    animation: shimmer 2s infinite linear;
}

.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
