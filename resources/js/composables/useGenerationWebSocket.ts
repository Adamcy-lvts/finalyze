import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Types
export interface GenerationStage {
    id: string
    name: string
    description: string
    status: 'pending' | 'active' | 'completed' | 'error'
    progress: number
    chapterProgress?: number
    wordCount?: number
    targetWordCount?: number
    generationTime?: number
    range: [number, number]
}

export interface ActivityLogEntry {
    timestamp: string
    type: 'info' | 'success' | 'error' | 'stage' | 'chapter_progress' | 'chapter_completed' | 'mining' | 'conversion'
    message: string
    chapter?: number
    chapterProgress?: number
    generationTime?: number
    source?: string
}

export interface GenerationState {
    status: 'not_started' | 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled'
    progress: number
    currentStage: string
    message: string
    isConnected: boolean
    error: string | null
}

export interface GenerationEventPayload {
    generation_id: number
    project_id: number
    status: string
    progress: number
    current_stage: string
    message: string
    timestamp: string
    event_type?: string
    // Chapter-specific
    chapter_number?: number
    chapter_title?: string
    chapter_progress?: number
    current_word_count?: number
    target_word_count?: number
    word_count?: number
    generation_time?: number
    total_chapters?: number
    chapters_completed?: number
    stage_description?: string
    // Literature mining
    source?: string
    papers_found?: number
    total_papers?: number
    sub_stage?: string
    // Completion
    total_word_count?: number
    total_duration?: number
    papers_collected?: number
    download_links?: { docx: string; pdf: string }
    // Failure
    error_message?: string
    failed_stage?: string
    failed_chapter?: number
    can_resume?: boolean
    last_successful_chapter?: number
    // Start
    is_resume?: boolean
    estimated_duration?: string
}

/**
 * Composable for managing WebSocket connection to generation progress channel
 */
export function useGenerationWebSocket(projectId: number) {
    // State
    const state = ref<GenerationState>({
        status: 'not_started',
        progress: 0,
        currentStage: '',
        message: '',
        isConnected: false,
        error: null,
    })

    const stages = ref<GenerationStage[]>([])
    const activityLog = ref<ActivityLogEntry[]>([])
    const metadata = ref<Record<string, any>>({})
    const downloadLinks = ref<{ docx: string; pdf: string } | null>(null)

    // Track known chapters for dynamic stage creation
    const knownChapters = new Set<number>()

    // Echo instance
    let echoInstance: Echo | null = null
    let channel: any = null

    // Computed
    const isGenerating = computed(() => 
        ['pending', 'processing'].includes(state.value.status)
    )

    const isCompleted = computed(() => state.value.status === 'completed')
    const hasFailed = computed(() => state.value.status === 'failed')
    const canResume = computed(() => 
        state.value.status === 'failed' && state.value.progress > 0
    )

    const estimatedTimeRemaining = computed(() => {
        if (isCompleted.value) return 'Completed'
        if (hasFailed.value) return 'Stopped'
        if (!isGenerating.value) return 'Est. 15-20m'

        const chapterTimings = metadata.value.chapter_timings || {}
        const completedChapters = Object.keys(chapterTimings).length
        const totalChapters = metadata.value.total_chapters || 5

        if (completedChapters > 0) {
            const timingValues = Object.values(chapterTimings) as number[]
            const avgTime = timingValues.reduce((a, b) => a + b, 0) / completedChapters
            const remaining = totalChapters - completedChapters
            const estimatedSeconds = remaining * avgTime + 60

            if (estimatedSeconds < 60) return '< 1 min remaining'
            return `~${Math.ceil(estimatedSeconds / 60)} min${estimatedSeconds >= 120 ? 's' : ''} remaining`
        }

        const remaining = 100 - state.value.progress
        const estimatedMinutes = Math.ceil((remaining / 100) * 20)
        return `~${estimatedMinutes} min${estimatedMinutes > 1 ? 's' : ''} remaining`
    })

    // Initialize Echo and connect to channel
    const connect = () => {
        if (echoInstance) {
            console.warn('Already connected to WebSocket')
            return
        }

        try {
            // Initialize Echo with Reverb configuration
            echoInstance = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            })

            // Subscribe to private channel
            channel = echoInstance.private(`project.${projectId}.generation`)

            // Connection events
            channel.subscribed(() => {
                console.log('✅ Connected to generation channel')
                state.value.isConnected = true
                state.value.error = null
            })

            channel.error((error: any) => {
                console.error('❌ Channel error:', error)
                state.value.error = 'WebSocket connection error'
            })

            // Register event listeners
            registerEventListeners()

            console.log('🔌 Connecting to WebSocket channel...')

        } catch (error) {
            console.error('Failed to initialize WebSocket:', error)
            state.value.error = 'Failed to connect to real-time updates'
        }
    }

    // Register all event listeners
    const registerEventListeners = () => {
        if (!channel) return

        // Generation started
        channel.listen('.generation.started', (event: GenerationEventPayload) => {
            console.log('📡 Generation started:', event)
            handleGenerationStarted(event)
        })

        // Literature mining progress
        channel.listen('.generation.literature_mining', (event: GenerationEventPayload) => {
            console.log('📚 Literature mining:', event)
            handleLiteratureMining(event)
        })

        // Chapter started
        channel.listen('.generation.chapter.started', (event: GenerationEventPayload) => {
            console.log('📝 Chapter started:', event)
            handleChapterStarted(event)
        })

        // Chapter progress
        channel.listen('.generation.chapter.progress', (event: GenerationEventPayload) => {
            console.log('⏳ Chapter progress:', event)
            handleChapterProgress(event)
        })

        // Chapter completed
        channel.listen('.generation.chapter.completed', (event: GenerationEventPayload) => {
            console.log('✅ Chapter completed:', event)
            handleChapterCompleted(event)
        })

        // Generation completed
        channel.listen('.generation.completed', (event: GenerationEventPayload) => {
            console.log('🎉 Generation completed:', event)
            handleGenerationCompleted(event)
        })

        // Generation failed
        channel.listen('.generation.failed', (event: GenerationEventPayload) => {
            console.log('❌ Generation failed:', event)
            handleGenerationFailed(event)
        })
    }

    // Event handlers
    const handleGenerationStarted = (event: GenerationEventPayload) => {
        state.value.status = 'processing'
        state.value.progress = event.progress
        state.value.currentStage = event.current_stage
        state.value.message = event.message

        metadata.value = {
            ...metadata.value,
            total_chapters: event.total_chapters,
            is_resume: event.is_resume,
            estimated_duration: event.estimated_duration,
        }

        // Initialize stages
        initializeStages(event.total_chapters || 5)

        addToActivityLog({
            type: event.is_resume ? 'info' : 'stage',
            message: event.is_resume 
                ? '🔄 Resuming generation sequence...' 
                : '🚀 Starting generation sequence...',
        })
    }

    const handleLiteratureMining = (event: GenerationEventPayload) => {
        state.value.progress = event.progress
        state.value.currentStage = 'literature_mining'
        state.value.message = event.message

        // Update literature mining stage
        const stage = stages.value.find(s => s.id === 'literature_mining')
        if (stage) {
            stage.status = 'active'
            stage.progress = ((event.progress - 0) / 20) * 100
            stage.description = event.message
        }

        addToActivityLog({
            type: 'mining',
            message: event.message,
            source: event.source,
        })

        // Mark as completed when done
        if (event.sub_stage === 'completed' && event.source === 'complete') {
            if (stage) {
                stage.status = 'completed'
                stage.progress = 100
            }
        }
    }

    const handleChapterStarted = (event: GenerationEventPayload) => {
        const chapterNum = event.chapter_number!
        
        state.value.progress = event.progress
        state.value.currentStage = `chapter_generation_${chapterNum}`
        state.value.message = event.message

        metadata.value = {
            ...metadata.value,
            current_chapter: chapterNum,
            total_chapters: event.total_chapters,
        }

        // Ensure chapter stage exists
        ensureChapterStage(chapterNum, event.chapter_title!, event.target_word_count!)

        // Mark previous stages as completed
        markPreviousStagesCompleted(chapterNum)

        // Update current stage
        const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
        if (stage) {
            stage.status = 'active'
            stage.progress = 0
            stage.chapterProgress = 0
        }

        addToActivityLog({
            type: 'chapter_progress',
            message: `📝 Starting Chapter ${chapterNum}: ${event.chapter_title}`,
            chapter: chapterNum,
        })
    }

    const handleChapterProgress = (event: GenerationEventPayload) => {
        const chapterNum = event.chapter_number!

        state.value.progress = event.progress
        state.value.message = event.stage_description || event.message

        if (chapterNum > 0) {
            const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
            if (stage) {
                stage.chapterProgress = event.chapter_progress
                stage.wordCount = event.current_word_count
                stage.description = event.stage_description || stage.description
            }

            metadata.value = {
                ...metadata.value,
                chapter_progress: event.chapter_progress,
                current_word_count: event.current_word_count,
            }
        }
    }

    const handleChapterCompleted = (event: GenerationEventPayload) => {
        const chapterNum = event.chapter_number!

        state.value.progress = event.progress
        state.value.message = event.message

        const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
        if (stage) {
            stage.status = 'completed'
            stage.progress = 100
            stage.chapterProgress = 100
            stage.wordCount = event.word_count
            stage.generationTime = event.generation_time
        }

        // Update timings for ETA
        const chapterTimings = metadata.value.chapter_timings || {}
        chapterTimings[chapterNum] = event.generation_time
        metadata.value = {
            ...metadata.value,
            chapter_timings: chapterTimings,
            chapters_completed: event.chapters_completed,
        }

        addToActivityLog({
            type: 'chapter_completed',
            message: `✅ Chapter ${chapterNum} completed (${event.word_count?.toLocaleString()} words in ${event.generation_time}s)`,
            chapter: chapterNum,
            generationTime: event.generation_time,
        })
    }

    const handleGenerationCompleted = (event: GenerationEventPayload) => {
        state.value.status = 'completed'
        state.value.progress = 100
        state.value.currentStage = 'completed'
        state.value.message = 'Project generation completed successfully!'

        // Mark HTML conversion as complete
        const conversionStage = stages.value.find(s => s.id === 'html_conversion')
        if (conversionStage) {
            conversionStage.status = 'completed'
            conversionStage.progress = 100
        }

        downloadLinks.value = event.download_links || null

        metadata.value = {
            ...metadata.value,
            total_word_count: event.total_word_count,
            total_duration: event.total_duration,
            papers_collected: event.papers_collected,
        }

        addToActivityLog({
            type: 'success',
            message: `🎉 Project generation completed! (${event.total_word_count?.toLocaleString()} words in ${Math.round(event.total_duration || 0)}s)`,
        })
    }

    const handleGenerationFailed = (event: GenerationEventPayload) => {
        state.value.status = 'failed'
        state.value.message = event.error_message || 'Generation failed'
        state.value.error = event.error_message || null

        // Mark current stage as error
        const currentStage = stages.value.find(s => s.id === event.failed_stage)
        if (currentStage) {
            currentStage.status = 'error'
        }

        metadata.value = {
            ...metadata.value,
            can_resume: event.can_resume,
            last_successful_chapter: event.last_successful_chapter,
        }

        addToActivityLog({
            type: 'error',
            message: `❌ Generation failed: ${event.error_message}`,
            chapter: event.failed_chapter,
        })
    }

    // Helper functions
    const initializeStages = (totalChapters: number) => {
        const progressPerChapter = 75 / totalChapters

        stages.value = [
            {
                id: 'literature_mining',
                name: 'Literature Mining',
                description: 'Collecting research papers from academic databases',
                status: 'pending',
                progress: 0,
                range: [0, 20],
            },
        ]

        // Add chapter stages
        for (let i = 1; i <= totalChapters; i++) {
            const start = 20 + ((i - 1) * progressPerChapter)
            const end = 20 + (i * progressPerChapter)

            stages.value.push({
                id: `chapter_generation_${i}`,
                name: `Chapter ${i}`,
                description: `Chapter ${i}`,
                status: 'pending',
                progress: 0,
                chapterProgress: 0,
                wordCount: 0,
                targetWordCount: 0,
                range: [start, end],
            })

            knownChapters.add(i)
        }

        // Add HTML conversion stage
        stages.value.push({
            id: 'html_conversion',
            name: 'Finalizing Project',
            description: 'Formatting content and preparing final document',
            status: 'pending',
            progress: 0,
            range: [95, 100],
        })
    }

    const ensureChapterStage = (chapterNum: number, title: string, targetWordCount: number) => {
        if (knownChapters.has(chapterNum)) {
            // Update existing stage
            const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
            if (stage) {
                stage.description = title
                stage.targetWordCount = targetWordCount
            }
            return
        }

        // Create new stage dynamically
        const totalChapters = Math.max(knownChapters.size + 1, chapterNum)
        const progressPerChapter = 75 / totalChapters
        const start = 20 + ((chapterNum - 1) * progressPerChapter)
        const end = 20 + (chapterNum * progressPerChapter)

        const newStage: GenerationStage = {
            id: `chapter_generation_${chapterNum}`,
            name: `Chapter ${chapterNum}`,
            description: title,
            status: 'pending',
            progress: 0,
            chapterProgress: 0,
            wordCount: 0,
            targetWordCount,
            range: [start, end],
        }

        // Insert before HTML conversion
        const conversionIndex = stages.value.findIndex(s => s.id === 'html_conversion')
        stages.value.splice(conversionIndex, 0, newStage)
        knownChapters.add(chapterNum)

        // Recalculate ranges for all chapter stages
        recalculateStageRanges()
    }

    const recalculateStageRanges = () => {
        const chapterStages = stages.value.filter(s => s.id.startsWith('chapter_generation_'))
        const totalChapters = chapterStages.length
        const progressPerChapter = 75 / totalChapters

        chapterStages.forEach((stage, index) => {
            const start = 20 + (index * progressPerChapter)
            const end = 20 + ((index + 1) * progressPerChapter)
            stage.range = [start, end]
        })
    }

    const markPreviousStagesCompleted = (currentChapter: number) => {
        // Mark literature mining as completed
        const miningStage = stages.value.find(s => s.id === 'literature_mining')
        if (miningStage && miningStage.status !== 'completed') {
            miningStage.status = 'completed'
            miningStage.progress = 100
        }

        // Mark previous chapters as completed
        for (let i = 1; i < currentChapter; i++) {
            const stage = stages.value.find(s => s.id === `chapter_generation_${i}`)
            if (stage && stage.status !== 'completed') {
                stage.status = 'completed'
                stage.progress = 100
                stage.chapterProgress = 100
            }
        }
    }

    const addToActivityLog = (entry: Omit<ActivityLogEntry, 'timestamp'>) => {
        activityLog.value.unshift({
            ...entry,
            timestamp: new Date().toISOString(),
        })

        // Keep only last 100 entries
        if (activityLog.value.length > 100) {
            activityLog.value.pop()
        }
    }

    // Disconnect from WebSocket
    const disconnect = () => {
        if (channel) {
            channel.stopListening('.generation.started')
            channel.stopListening('.generation.literature_mining')
            channel.stopListening('.generation.chapter.started')
            channel.stopListening('.generation.chapter.progress')
            channel.stopListening('.generation.chapter.completed')
            channel.stopListening('.generation.completed')
            channel.stopListening('.generation.failed')
        }

        if (echoInstance) {
            echoInstance.leave(`project.${projectId}.generation`)
            echoInstance = null
        }

        channel = null
        state.value.isConnected = false
        console.log('🔌 Disconnected from generation channel')
    }

    // Reset state for new generation
    const reset = () => {
        state.value = {
            status: 'not_started',
            progress: 0,
            currentStage: '',
            message: '',
            isConnected: state.value.isConnected,
            error: null,
        }
        stages.value = []
        activityLog.value = []
        metadata.value = {}
        downloadLinks.value = null
        knownChapters.clear()
    }

    // Lifecycle
    onMounted(() => {
        connect()
    })

    onUnmounted(() => {
        disconnect()
    })

    return {
        // State
        state,
        stages,
        activityLog,
        metadata,
        downloadLinks,

        // Computed
        isGenerating,
        isCompleted,
        hasFailed,
        canResume,
        estimatedTimeRemaining,

        // Methods
        connect,
        disconnect,
        reset,
    }
}
