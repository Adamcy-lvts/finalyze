import { ref, computed, onMounted, onUnmounted } from 'vue'
import Echo from 'laravel-echo'
import axios from 'axios'

// Pusher is imported by Echo internally

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
    let echoInstance: Echo<any> | null = null
    let channel: any = null

    // Polling fallback
    let pollingInterval: ReturnType<typeof setInterval> | null = null
    const POLLING_INTERVAL_MS = 5000 // Poll every 5 seconds as fallback

    // Progress animation for smooth visual feedback
    let progressAnimationInterval: ReturnType<typeof setInterval> | null = null
    const PROGRESS_ANIMATION_MS = 2000 // Animate progress every 2 seconds

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

        // Start polling as fallback for missed WebSocket events
        startPolling()

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

        // Stop any previous animation
        stopProgressAnimation()

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
            stage.chapterProgress = 10 // Start at 10% (initializing)
        }

        // Start minimal fallback animation only if no real progress arrives
        // This will be overridden by real progress events from WebSocket
        startProgressAnimation(chapterNum, 30) // Only animate to 30% as fallback

        addToActivityLog({
            type: 'chapter_progress',
            message: `📝 Starting Chapter ${chapterNum}: ${event.chapter_title}`,
            chapter: chapterNum,
        })
    }

    const handleChapterProgress = (event: GenerationEventPayload) => {
        const chapterNum = event.chapter_number!

        // Stop fallback animation - we're receiving real progress data
        stopProgressAnimation()

        state.value.progress = event.progress
        state.value.message = event.stage_description || event.message

        if (chapterNum > 0) {
            const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
            if (stage) {
                // Use real progress data from WebSocket
                stage.chapterProgress = event.chapter_progress
                stage.wordCount = event.current_word_count
                stage.description = event.stage_description || stage.description
            }

            metadata.value = {
                ...metadata.value,
                chapter_progress: event.chapter_progress,
                current_word_count: event.current_word_count,
            }

            // Log real progress for debugging
            console.log(`📊 Real progress: Chapter ${chapterNum} - ${event.chapter_progress}% (${event.current_word_count} words)`)
        }
    }

    const handleChapterCompleted = (event: GenerationEventPayload) => {
        const chapterNum = event.chapter_number!

        // Stop progress animation for this chapter
        stopProgressAnimation()

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

        // Stop polling and animation - generation is done
        stopPolling()
        stopProgressAnimation()

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

        // Stop polling and animation - generation failed
        stopPolling()
        stopProgressAnimation()

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

    const mapDetailTypeToLogType = (type: string): ActivityLogEntry['type'] => {
        switch (type) {
            case 'success':
                return 'success'
            case 'error':
                return 'error'
            case 'chapter_completed':
                return 'chapter_completed'
            case 'chapter_started':
            case 'chapter_progress':
                return 'chapter_progress'
            case 'mining':
                return 'mining'
            case 'conversion':
                return 'conversion'
            case 'stage':
                return 'stage'
            default:
                return 'info'
        }
    }

    const restoreFromApiData = (apiData: {
        status: GenerationState['status']
        progress: number
        current_stage: string
        message: string
        details?: any[]
        metadata?: Record<string, any>
        chapter_statuses?: {
            chapter_number: number
            title?: string
            status?: string
            word_count?: number
            target_word_count?: number
            is_completed?: boolean
        }[]
        download_links?: { docx: string; pdf: string } | null
    }) => {
        state.value.status = apiData.status
        state.value.progress = apiData.progress ?? 0
        state.value.currentStage = apiData.current_stage || ''
        state.value.message = apiData.message || ''
        state.value.error = apiData.status === 'failed' ? apiData.message : null

        metadata.value = apiData.metadata || {}
        downloadLinks.value = apiData.download_links || null

        knownChapters.clear()

        const totalChapters =
            apiData.chapter_statuses?.length ||
            metadata.value.total_chapters ||
            5

        initializeStages(totalChapters)

        // Keep metadata aligned with the number of chapters we restored
        metadata.value = {
            ...metadata.value,
            total_chapters: metadata.value.total_chapters || totalChapters,
        }

        apiData.chapter_statuses?.forEach(chapter => {
            const stage = stages.value.find(s => s.id === `chapter_generation_${chapter.chapter_number}`)
            if (!stage) return

            stage.description = chapter.title || stage.description
            stage.targetWordCount = chapter.target_word_count ?? stage.targetWordCount

            if (chapter.is_completed || chapter.status === 'completed') {
                stage.status = 'completed'
                stage.progress = 100
                stage.chapterProgress = 100
                stage.wordCount = chapter.word_count
                stage.generationTime = metadata.value?.chapter_timings?.[chapter.chapter_number]
            }
        })

        if (['processing', 'pending'].includes(apiData.status)) {
            if (apiData.current_stage) {
                const currentStage = stages.value.find(s => s.id === apiData.current_stage)
                if (currentStage) {
                    currentStage.status = 'active'

                    if (currentStage.id.startsWith('chapter_generation_')) {
                        const chapterNum = Number(apiData.current_stage.replace('chapter_generation_', ''))
                        const chapterProgress = apiData.metadata?.chapter_progress ?? metadata.value.chapter_progress ?? 0
                        const currentWordCount = apiData.metadata?.current_word_count ?? metadata.value.current_word_count

                        metadata.value = {
                            ...metadata.value,
                            current_chapter: chapterNum,
                            chapter_progress: chapterProgress,
                            current_word_count: currentWordCount,
                        }

                        currentStage.chapterProgress = chapterProgress
                        currentStage.wordCount = currentWordCount ?? currentStage.wordCount

                        startProgressAnimation(chapterNum, 30) // Minimal fallback
                    }
                }

                // If we're past literature mining, ensure it shows as completed
                if (apiData.current_stage.includes('chapter_generation')) {
                    const miningStage = stages.value.find(s => s.id === 'literature_mining')
                    if (miningStage) {
                        miningStage.status = 'completed'
                        miningStage.progress = 100
                    }
                }

                if (apiData.current_stage === 'html_conversion') {
                    const conversionStage = stages.value.find(s => s.id === 'html_conversion')
                    if (conversionStage) {
                        conversionStage.status = 'active'
                        conversionStage.progress = apiData.progress || conversionStage.progress
                    }
                }
            }

            startPolling()
        }

        if (apiData.status === 'completed') {
            state.value.progress = 100
            stages.value.forEach(stage => {
                stage.status = 'completed'
                stage.progress = 100
                if (stage.chapterProgress !== undefined) {
                    stage.chapterProgress = 100
                }
            })
        }

        if (apiData.status === 'failed' && apiData.current_stage) {
            const failedStage = stages.value.find(s => s.id === apiData.current_stage)
            if (failedStage) {
                failedStage.status = 'error'
            }
        }

        if (apiData.details?.length) {
            activityLog.value = apiData.details
                .map(detail => ({
                    timestamp: detail.timestamp,
                    type: mapDetailTypeToLogType(detail.type),
                    message: detail.message,
                    chapter: detail.chapter,
                    generationTime: detail.generation_time,
                    source: detail.source,
                }))
                .reverse()
        }
    }

    // Start polling fallback for robustness
    const startPolling = () => {
        if (pollingInterval) return // Already polling

        console.log('📡 Starting polling fallback')
        pollingInterval = setInterval(async () => {
            if (!isGenerating.value) {
                stopPolling()
                return
            }

            try {
                const response = await axios.get(`/api/projects/${projectId}/bulk-generate/status`)
                const data = response.data

                // Only update if we have newer data (higher progress or different status)
                if (data.progress > state.value.progress || data.status !== state.value.status) {
                    console.log('📊 Polling update received:', {
                        progress: data.progress,
                        status: data.status,
                        stage: data.current_stage,
                    })

                    state.value.progress = data.progress
                    state.value.status = data.status
                    state.value.message = data.message
                    state.value.currentStage = data.current_stage

                    // Update metadata
                    if (data.metadata) {
                        metadata.value = { ...metadata.value, ...data.metadata }

                        // Update chapter progress from metadata
                        if (data.metadata.chapter_progress !== undefined && data.metadata.current_chapter) {
                            const chapterNum = data.metadata.current_chapter
                            const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
                            if (stage) {
                                stage.chapterProgress = data.metadata.chapter_progress
                                stage.wordCount = data.metadata.current_word_count || 0
                            }
                        }
                    }

                    // Handle completion
                    if (data.status === 'completed') {
                        downloadLinks.value = data.download_links
                        stopPolling()
                    }

                    // Handle failure
                    if (data.status === 'failed') {
                        state.value.error = data.message
                        stopPolling()
                    }
                }
            } catch (error) {
                console.warn('Polling request failed:', error)
            }
        }, POLLING_INTERVAL_MS)
    }

    // Stop polling
    const stopPolling = () => {
        if (pollingInterval) {
            clearInterval(pollingInterval)
            pollingInterval = null
            console.log('📡 Polling stopped')
        }
    }

    // Minimal fallback animation - only runs if no real WebSocket progress arrives
    // Real progress events will stop this animation and use actual word counts
    const startProgressAnimation = (chapterNum: number, targetProgress: number = 30) => {
        stopProgressAnimation()

        const stage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
        if (!stage) return

        const progressIncrement = 1 // Slower increment - just a fallback
        let tickCount = 0
        const maxTicks = 15 // Stop after ~30 seconds if no real progress arrives

        console.log(`🎬 Starting fallback animation for Chapter ${chapterNum} (will be overridden by real progress)`)

        progressAnimationInterval = setInterval(() => {
            tickCount++

            const currentStage = stages.value.find(s => s.id === `chapter_generation_${chapterNum}`)
            if (!currentStage || currentStage.status !== 'active') {
                stopProgressAnimation()
                return
            }

            // Stop fallback after a while - real progress should have arrived by then
            if (tickCount >= maxTicks) {
                console.log(`⏹️ Fallback animation stopped - waiting for real WebSocket progress`)
                stopProgressAnimation()
                return
            }

            // Only animate if we haven't received real progress yet
            // Real progress will have higher values due to actual word counts
            const current = currentStage.chapterProgress || 0
            if (current < targetProgress) {
                currentStage.chapterProgress = Math.min(current + progressIncrement, targetProgress)
                // Don't fake word counts - leave them as received from real events
            }
        }, PROGRESS_ANIMATION_MS)
    }

    // Stop progress animation
    const stopProgressAnimation = () => {
        if (progressAnimationInterval) {
            clearInterval(progressAnimationInterval)
            progressAnimationInterval = null
        }
    }

    // Disconnect from WebSocket
    const disconnect = () => {
        stopPolling()
        stopProgressAnimation()

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
        restoreFromApiData,
        reset,
        startPolling,
    }
}
