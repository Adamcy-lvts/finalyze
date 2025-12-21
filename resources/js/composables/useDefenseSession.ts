import axios from 'axios'
import { computed, ref } from 'vue'
import type {
    DefenseFeedback,
    DefenseMessage,
    DefensePerformanceMetrics,
    DefenseSession,
} from '@/types/defense'

interface StartSessionPayload {
    selected_panelists: string[]
    difficulty_level?: 'undergraduate' | 'masters' | 'doctoral'
    time_limit_minutes?: number | null
    question_limit?: number | null
}

export function useDefenseSession(projectId: number) {
    const session = ref<DefenseSession | null>(null)
    const messages = ref<DefenseMessage[]>([])
    const feedback = ref<DefenseFeedback | null>(null)
    const performanceMetrics = ref<DefensePerformanceMetrics | null>(null)
    const isStarting = ref(false)
    const isLoading = ref(false)
    const isSending = ref(false)

    const hasSession = computed(() => !!session.value)

    const startSession = async (payload: StartSessionPayload) => {
        if (isStarting.value) return
        isStarting.value = true
        try {
            const { data } = await axios.post(`/api/projects/${projectId}/defense/sessions`, payload)
            session.value = data.session
            messages.value = []
            feedback.value = null
            performanceMetrics.value = session.value?.performance_metrics ?? null
            return data.session as DefenseSession
        } finally {
            isStarting.value = false
        }
    }

    const loadSession = async (sessionId: number) => {
        if (isLoading.value) return
        isLoading.value = true
        try {
            const { data } = await axios.get(`/api/projects/${projectId}/defense/sessions/${sessionId}`)
            session.value = data.session
            messages.value = data.session?.messages ?? []
            feedback.value = data.session?.feedback ?? null
            performanceMetrics.value = data.session?.performance_metrics ?? null
        } finally {
            isLoading.value = false
        }
    }

    const getNextQuestion = async (sessionId: number, persona?: string, requestHint?: boolean) => {
        const { data } = await axios.get(
            `/api/projects/${projectId}/defense/sessions/${sessionId}/next-question`,
            {
                params: {
                    ...(persona ? { persona } : {}),
                    ...(requestHint ? { request_hint: 1 } : {}),
                },
            }
        )
        messages.value.push(data.message)
        session.value = data.session
        return data.message as DefenseMessage
    }

    const sendResponse = async (sessionId: number, response: string, responseTimeMs?: number) => {
        if (isSending.value) return
        isSending.value = true
        const optimisticId = -Date.now()
        const optimisticMessage: DefenseMessage = {
            id: optimisticId,
            session_id: sessionId,
            role: 'student',
            panelist_persona: null,
            content: response,
            response_time_ms: responseTimeMs ?? null,
        }
        messages.value.push(optimisticMessage)
        try {
            const { data } = await axios.post(`/api/projects/${projectId}/defense/sessions/${sessionId}/respond`, {
                response,
                response_time_ms: responseTimeMs,
            })

            const optimisticIndex = messages.value.findIndex(message => message.id === optimisticId)
            if (optimisticIndex >= 0) {
                messages.value[optimisticIndex] = data.message
            } else {
                messages.value.push(data.message)
            }
            performanceMetrics.value = data.performance_metrics
            return data
        } catch (error) {
            messages.value = messages.value.filter(message => message.id !== optimisticId)
            throw error
        } finally {
            isSending.value = false
        }
    }

    const endSession = async (sessionId: number) => {
        const { data } = await axios.post(`/api/projects/${projectId}/defense/sessions/${sessionId}/end`)
        session.value = data.session
        feedback.value = data.feedback
        performanceMetrics.value = data.session?.performance_metrics ?? null
        return data.feedback as DefenseFeedback
    }

    const loadTranscript = async (sessionId: number) => {
        const { data } = await axios.get(`/api/projects/${projectId}/defense/sessions/${sessionId}/transcript`)
        messages.value = data.messages
        return data.messages as DefenseMessage[]
    }

    return {
        session,
        messages,
        feedback,
        performanceMetrics,
        isStarting,
        isLoading,
        isSending,
        hasSession,
        startSession,
        loadSession,
        getNextQuestion,
        sendResponse,
        endSession,
        loadTranscript,
    }
}
