/**
 * Robust Streaming Generation Composable
 *
 * Handles AI content streaming with:
 * - State machine for lifecycle management
 * - Automatic reconnection with exponential backoff
 * - Chunk accumulation for smooth rendering
 * - Connection quality monitoring
 * - Content integrity verification
 */

import { ref, computed, onUnmounted, readonly } from 'vue'

// Types
export type StreamStatus =
  | 'idle'
  | 'checking_connection'
  | 'connecting'
  | 'streaming'
  | 'paused'
  | 'reconnecting'
  | 'error'
  | 'complete'

export interface StreamError {
  code: string
  message: string
  timestamp: number
  recoverable: boolean
}

export interface StreamProgress {
  wordCount: number
  targetWordCount: number
  percentage: number
  phase: string
  message: string
}

export interface StreamConfig {
  maxReconnectAttempts: number
  reconnectBaseDelay: number
  reconnectMaxDelay: number
  jitterFactor: number
  chunkBatchSize: number
  renderInterval: number
  heartbeatTimeout: number
  connectionCheckTimeout: number
}

export interface StreamCallbacks {
  onContent: (content: string, wordCount: number) => void
  onProgress: (progress: StreamProgress) => void
  onComplete: (finalContent: string, wordCount: number) => void
  onError: (error: StreamError) => void
  onReconnecting: (attempt: number, maxAttempts: number) => void
  onAutoSave: (wordCount: number) => void
}

const defaultConfig: StreamConfig = {
  maxReconnectAttempts: 5,
  reconnectBaseDelay: 1000,
  reconnectMaxDelay: 30000,
  jitterFactor: 0.3,
  chunkBatchSize: 3,
  renderInterval: 100,
  heartbeatTimeout: 30000,
  connectionCheckTimeout: 5000,
}

export function useStreamingGeneration(
  callbacks: Partial<StreamCallbacks> = {},
  userConfig: Partial<StreamConfig> = {}
) {
  const config: StreamConfig = { ...defaultConfig, ...userConfig }

  // Core state
  const status = ref<StreamStatus>('idle')
  const buffer = ref<string>('')
  const wordCount = ref<number>(0)
  const serverAckWordCount = ref<number>(0)
  const generationId = ref<string | null>(null)
  const reconnectAttempts = ref<number>(0)
  const errors = ref<StreamError[]>([])
  const progress = ref<StreamProgress>({
    wordCount: 0,
    targetWordCount: 0,
    percentage: 0,
    phase: '',
    message: '',
  })

  // Internal refs
  let eventSource: EventSource | null = null
  let heartbeatTimer: ReturnType<typeof setTimeout> | null = null
  let renderTimer: ReturnType<typeof setTimeout> | null = null
  let chunkBuffer: string[] = []
  let lastRenderTime = 0
  let pendingRender = ''
  let currentUrl = ''
  let currentParams: Record<string, string> = {}
  let isDestroyed = false

  // Computed
  const isStreaming = computed(() => status.value === 'streaming')
  const isConnecting = computed(() =>
    status.value === 'connecting' || status.value === 'checking_connection'
  )
  const isReconnecting = computed(() => status.value === 'reconnecting')
  const hasError = computed(() => status.value === 'error')
  const isComplete = computed(() => status.value === 'complete')
  const canRetry = computed(() =>
    status.value === 'error' && reconnectAttempts.value < config.maxReconnectAttempts
  )

  // Connection quality check
  async function checkConnectionQuality(): Promise<boolean> {
    if (!navigator.onLine) {
      addError({
        code: 'OFFLINE',
        message: 'No internet connection',
        recoverable: true,
      })
      return false
    }

    // Check connection type if available
    const connection = (navigator as any).connection ||
                       (navigator as any).mozConnection ||
                       (navigator as any).webkitConnection

    if (connection) {
      const effectiveType = connection.effectiveType
      if (effectiveType === 'slow-2g' || effectiveType === '2g') {
        console.warn('Slow connection detected:', effectiveType)
        // Don't fail, just warn
      }
    }

    // Quick ping test
    try {
      const controller = new AbortController()
      const timeoutId = setTimeout(() => controller.abort(), config.connectionCheckTimeout)

      const pingStart = performance.now()
      const response = await fetch('/api/ping', {
        method: 'HEAD',
        cache: 'no-cache',
        signal: controller.signal,
      })
      clearTimeout(timeoutId)

      const latency = performance.now() - pingStart
      console.log('Server latency:', Math.round(latency), 'ms')

      return response.ok
    } catch (error) {
      // Ping failed but we might still be able to stream
      console.warn('Connection check failed, proceeding anyway:', error)
      return true
    }
  }

  // Start streaming
  async function start(
    url: string,
    params: Record<string, string> = {},
    targetWordCount: number = 0
  ): Promise<void> {
    if (isDestroyed) return

    // Store for potential reconnection
    currentUrl = url
    currentParams = params

    // Reset state
    reset()
    status.value = 'checking_connection'
    progress.value.targetWordCount = targetWordCount

    // Check connection
    const connectionOk = await checkConnectionQuality()
    if (!connectionOk && !navigator.onLine) {
      status.value = 'error'
      return
    }

    // Connect
    connect()
  }

  function connect(): void {
    if (isDestroyed) return

    status.value = 'connecting'

    // Build URL with params
    const urlParams = new URLSearchParams(currentParams)

    // Add resume info if reconnecting
    if (reconnectAttempts.value > 0 && wordCount.value > 0) {
      urlParams.set('resume_from', wordCount.value.toString())
      if (generationId.value) {
        urlParams.set('generation_id', generationId.value)
      }
    }

    const fullUrl = `${currentUrl}?${urlParams.toString()}`
    console.log('Connecting to stream:', fullUrl)

    try {
      eventSource = new EventSource(fullUrl)
      setupEventHandlers()
      startHeartbeatMonitor()
    } catch (error) {
      handleConnectionError(error as Error)
    }
  }

  function setupEventHandlers(): void {
    if (!eventSource) return

    eventSource.onopen = () => {
      console.log('Stream connected')
      status.value = 'streaming'
      reconnectAttempts.value = 0
      resetHeartbeatMonitor()
    }

    eventSource.onmessage = (event) => {
      if (isDestroyed) return

      resetHeartbeatMonitor()

      try {
        const data = JSON.parse(event.data)
        handleMessage(data)
      } catch (error) {
        console.error('Failed to parse SSE message:', error)
      }
    }

    eventSource.onerror = (error) => {
      console.error('EventSource error:', error)
      handleConnectionError(new Error('Stream connection error'))
    }
  }

  function handleMessage(data: any): void {
    switch (data.type) {
      case 'start':
        status.value = 'streaming'
        generationId.value = data.generation_id || null
        updateProgress('Initializing', data.message || 'Starting generation...')
        break

      case 'content':
        handleContentChunk(data)
        break

      case 'heartbeat':
        // Connection alive, update server ack
        if (data.last_saved_words) {
          serverAckWordCount.value = data.last_saved_words
        }
        break

      case 'autosave':
        serverAckWordCount.value = data.word_count || serverAckWordCount.value
        callbacks.onAutoSave?.(data.word_count)
        break

      case 'complete':
        handleComplete(data)
        break

      case 'error':
        handleServerError(data)
        break

      case 'end':
        // Stream ended gracefully
        if (status.value !== 'complete' && status.value !== 'error') {
          handleComplete({ final_word_count: wordCount.value })
        }
        break
    }
  }

  function handleContentChunk(data: any): void {
    const chunk = data.content || ''
    if (!chunk) return

    // Add to buffer immediately
    buffer.value += chunk
    chunkBuffer.push(chunk)
    pendingRender += chunk

    // Update word count
    wordCount.value = data.word_count || countWords(buffer.value)

    // Verify server word count if provided
    if (data.word_count && Math.abs(wordCount.value - data.word_count) > 10) {
      console.warn('Word count mismatch:', {
        local: wordCount.value,
        server: data.word_count,
      })
    }

    // Update progress
    const pct = progress.value.targetWordCount > 0
      ? Math.min((wordCount.value / progress.value.targetWordCount) * 100, 99)
      : 0

    updateProgress(
      'Generating',
      `Generating content... (${wordCount.value} words)`,
      pct
    )

    // Schedule batched render
    scheduleRender()
  }

  function scheduleRender(): void {
    if (renderTimer) return

    const now = performance.now()
    const timeSinceLastRender = now - lastRenderTime

    if (timeSinceLastRender >= config.renderInterval && chunkBuffer.length >= config.chunkBatchSize) {
      flushRender()
    } else {
      const delay = Math.max(0, config.renderInterval - timeSinceLastRender)
      renderTimer = setTimeout(() => {
        renderTimer = null
        flushRender()
      }, delay)
    }
  }

  function flushRender(): void {
    if (!pendingRender || isDestroyed) return

    const content = buffer.value
    const currentWordCount = wordCount.value

    pendingRender = ''
    chunkBuffer = []
    lastRenderTime = performance.now()

    // Use requestAnimationFrame for smooth rendering
    requestAnimationFrame(() => {
      callbacks.onContent?.(content, currentWordCount)
    })
  }

  function handleComplete(data: any): void {
    status.value = 'complete'

    // Final flush
    if (pendingRender) {
      flushRender()
    }

    const finalWordCount = data.final_word_count || wordCount.value
    wordCount.value = finalWordCount
    serverAckWordCount.value = finalWordCount

    updateProgress('Complete', `Generated ${finalWordCount} words`, 100)

    cleanup()
    callbacks.onComplete?.(buffer.value, finalWordCount)
  }

  function handleServerError(data: any): void {
    const error: StreamError = {
      code: data.code || 'SERVER_ERROR',
      message: data.message || 'Server error',
      timestamp: Date.now(),
      recoverable: data.can_resume || false,
    }

    addError(error)

    if (data.partial_saved && data.saved_word_count) {
      serverAckWordCount.value = data.saved_word_count
    }

    if (error.recoverable && reconnectAttempts.value < config.maxReconnectAttempts) {
      attemptReconnect()
    } else {
      status.value = 'error'
      cleanup()
      callbacks.onError?.(error)
    }
  }

  function handleConnectionError(error: Error): void {
    console.error('Connection error:', error.message)

    const streamError: StreamError = {
      code: 'CONNECTION_ERROR',
      message: error.message,
      timestamp: Date.now(),
      recoverable: true,
    }

    addError(streamError)

    if (reconnectAttempts.value < config.maxReconnectAttempts) {
      attemptReconnect()
    } else {
      status.value = 'error'
      cleanup()
      callbacks.onError?.(streamError)
    }
  }

  async function attemptReconnect(): Promise<void> {
    if (isDestroyed) return

    // Close existing connection
    closeEventSource()

    reconnectAttempts.value++
    status.value = 'reconnecting'

    callbacks.onReconnecting?.(reconnectAttempts.value, config.maxReconnectAttempts)
    updateProgress(
      'Reconnecting',
      `Reconnecting... (${reconnectAttempts.value}/${config.maxReconnectAttempts})`
    )

    // Calculate delay with exponential backoff + jitter
    const baseDelay = Math.min(
      config.reconnectBaseDelay * Math.pow(2, reconnectAttempts.value - 1),
      config.reconnectMaxDelay
    )
    const jitter = baseDelay * config.jitterFactor * Math.random()
    const delay = baseDelay + jitter

    console.log(`Reconnecting in ${Math.round(delay)}ms (attempt ${reconnectAttempts.value})`)

    // Wait for online if offline
    if (!navigator.onLine) {
      await waitForOnline()
    }

    // Wait for delay
    await new Promise(resolve => setTimeout(resolve, delay))

    if (isDestroyed) return

    // Try to reconnect
    connect()
  }

  function waitForOnline(): Promise<void> {
    return new Promise(resolve => {
      if (navigator.onLine) {
        resolve()
        return
      }

      const handler = () => {
        window.removeEventListener('online', handler)
        resolve()
      }
      window.addEventListener('online', handler)

      // Also resolve after a timeout
      setTimeout(() => {
        window.removeEventListener('online', handler)
        resolve()
      }, 30000)
    })
  }

  function startHeartbeatMonitor(): void {
    stopHeartbeatMonitor()

    heartbeatTimer = setTimeout(() => {
      if (status.value === 'streaming') {
        console.warn('Heartbeat timeout - no message received')
        handleConnectionError(new Error('Heartbeat timeout'))
      }
    }, config.heartbeatTimeout)
  }

  function resetHeartbeatMonitor(): void {
    if (status.value === 'streaming' || status.value === 'connecting') {
      startHeartbeatMonitor()
    }
  }

  function stopHeartbeatMonitor(): void {
    if (heartbeatTimer) {
      clearTimeout(heartbeatTimer)
      heartbeatTimer = null
    }
  }

  function updateProgress(phase: string, message: string, percentage?: number): void {
    progress.value = {
      ...progress.value,
      phase,
      message,
      wordCount: wordCount.value,
      percentage: percentage ?? progress.value.percentage,
    }
    callbacks.onProgress?.(progress.value)
  }

  function addError(error: Omit<StreamError, 'timestamp'>): void {
    errors.value.push({
      ...error,
      timestamp: Date.now(),
    })
  }

  function countWords(text: string): number {
    return text.split(/\s+/).filter(word => word.length > 0).length
  }

  function closeEventSource(): void {
    if (eventSource) {
      eventSource.close()
      eventSource = null
    }
  }

  function cleanup(): void {
    closeEventSource()
    stopHeartbeatMonitor()

    if (renderTimer) {
      clearTimeout(renderTimer)
      renderTimer = null
    }
  }

  function reset(): void {
    cleanup()
    buffer.value = ''
    wordCount.value = 0
    serverAckWordCount.value = 0
    generationId.value = null
    reconnectAttempts.value = 0
    errors.value = []
    chunkBuffer = []
    pendingRender = ''
    lastRenderTime = 0
    progress.value = {
      wordCount: 0,
      targetWordCount: progress.value.targetWordCount,
      percentage: 0,
      phase: '',
      message: '',
    }
  }

  function stop(): void {
    if (status.value === 'streaming' || status.value === 'connecting') {
      status.value = 'idle'
    }
    cleanup()
  }

  function pause(): void {
    if (status.value === 'streaming') {
      status.value = 'paused'
      closeEventSource()
      stopHeartbeatMonitor()
    }
  }

  function resume(): void {
    if (status.value === 'paused') {
      connect()
    }
  }

  function retry(): void {
    if (canRetry.value) {
      reconnectAttempts.value = 0
      connect()
    }
  }

  // Get current content (for saving)
  function getContent(): string {
    return buffer.value
  }

  // Get word count
  function getWordCount(): number {
    return wordCount.value
  }

  // Cleanup on unmount
  onUnmounted(() => {
    isDestroyed = true
    cleanup()
  })

  return {
    // State (readonly)
    status: readonly(status),
    buffer: readonly(buffer),
    wordCount: readonly(wordCount),
    serverAckWordCount: readonly(serverAckWordCount),
    generationId: readonly(generationId),
    progress: readonly(progress),
    errors: readonly(errors),

    // Computed
    isStreaming,
    isConnecting,
    isReconnecting,
    hasError,
    isComplete,
    canRetry,

    // Methods
    start,
    stop,
    pause,
    resume,
    retry,
    reset,
    getContent,
    getWordCount,
  }
}
