import { ref, watch } from 'vue'
import { debounce } from 'lodash'
import { route } from 'ziggy-js'
import { countWords } from '@/utils/wordCount'
import { recordWordUsage } from '@/composables/useWordBalance'

export interface ProgressiveStep {
    id: string
    text: string
    completed: boolean
    priority?: 'critical' | 'optional'
    action?: 'none' | 'open_citation_helper' | 'insert_text'
    payload?: { text?: string } | null
}

export interface WritingMilestone {
    id: string
    label: string
    completed: boolean
}

export interface ProgressiveGuidanceData {
    guidance_id?: number
    stage: string
    stage_label: string
    completion_percentage: number
    next_steps: ProgressiveStep[]
    contextual_tip: string | null
    writing_milestones: WritingMilestone[]
    completed_step_ids?: string[]
}

export function useProgressiveGuidance(projectSlug: string, chapterNumber: number, initialGuidance?: ProgressiveGuidanceData | null) {
    const guidance = ref<ProgressiveGuidanceData | null>(null)
    const isLoadingGuidance = ref(false)
    const lastRequestTime = ref<number>(0)
    const lastChargedFingerprint = ref<string>('')
    const lastKnownCompletedIds = ref<string[]>([])

    const storageKey = `progressive-guidance-${projectSlug}-${chapterNumber}`

    const getStoredCompletedStepIds = (): string[] => {
        try {
            const stored = localStorage.getItem(storageKey)
            if (!stored) return []
            const parsed = JSON.parse(stored)
            if (!Array.isArray(parsed)) return []
            return parsed.filter((v) => typeof v === 'string' && v.length > 0)
        } catch {
            return []
        }
    }

    const setStoredCompletedStepIds = (ids: string[]) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(Array.from(new Set(ids))))
        } catch {
            // ignore
        }
    }

    const clearStoredCompletedStepIds = () => {
        try {
            localStorage.removeItem(storageKey)
        } catch {
            // ignore
        }
    }

    const persistCompletedSteps = async () => {
        const completed = getStoredCompletedStepIds()

        try {
            await fetch(
                route('projects.manual-editor.progressive-guidance.steps', {
                    project: projectSlug,
                    chapter: chapterNumber,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    },
                    body: JSON.stringify({ completed_step_ids: completed }),
                }
            )
        } catch (e) {
            console.error('Failed to persist completed steps:', e)
        }
    }

    const debouncedPersistCompletedSteps = debounce(persistCompletedSteps, 400)

    const extractOutlineFromHtml = (html: string): string[] => {
        try {
            const parser = new DOMParser()
            const doc = parser.parseFromString(html || '', 'text/html')
            const nodes = Array.from(doc.querySelectorAll('h1,h2,h3,h4,h5,h6'))
            const headings = nodes
                .map((n) => (n.textContent || '').trim())
                .filter(Boolean)
                .slice(0, 20)
            return headings
        } catch {
            return []
        }
    }

    const extractContentExcerpt = (html: string): string => {
        try {
            const parser = new DOMParser()
            const doc = parser.parseFromString(html || '', 'text/html')
            const text = (doc.body?.textContent || '').replace(/\s+/g, ' ').trim()
            if (text.length <= 2000) return text
            return text.slice(-2000)
        } catch {
            return (html || '').slice(-2000)
        }
    }

    if (initialGuidance) {
        guidance.value = initialGuidance
        const initialCompleted =
            Array.isArray(initialGuidance.completed_step_ids) && initialGuidance.completed_step_ids.length
                ? initialGuidance.completed_step_ids
                : (initialGuidance.next_steps || []).filter((s) => s.completed).map((s) => s.id)
        if (initialCompleted.length) {
            lastKnownCompletedIds.value = initialCompleted
            setStoredCompletedStepIds(initialCompleted)
        }
    }

    /**
     * Request progressive guidance from backend
     */
    const requestGuidance = async (analysis: Record<string, any>, content: string) => {
        // Minimum word count to trigger guidance
        if (analysis.word_count === undefined || analysis.word_count < 0) {
            return
        }

        // Prevent too-frequent requests (min 3 seconds between requests)
        const now = Date.now()
        if (now - lastRequestTime.value < 3000) {
            return
        }

        isLoadingGuidance.value = true
        lastRequestTime.value = now

        try {
            const completedFromStorage = getStoredCompletedStepIds()
            const outline = extractOutlineFromHtml(content)
            const contentExcerpt = extractContentExcerpt(content)

            const response = await fetch(
                route('projects.manual-editor.progressive-guidance', {
                    project: projectSlug,
                    chapter: chapterNumber,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    },
                    body: JSON.stringify({
                        analysis: {
                            ...analysis,
                            outline,
                            content_excerpt: contentExcerpt,
                            completed_step_ids: completedFromStorage,
                        },
                    }),
                }
            )

            if (response.ok) {
                const data = await response.json()
                guidance.value = data

                if (Array.isArray(data?.completed_step_ids)) {
                    lastKnownCompletedIds.value = data.completed_step_ids
                    setStoredCompletedStepIds(lastKnownCompletedIds.value)
                }

                // Load completed steps from localStorage (in case server has none yet)
                loadCompletedSteps()

                const fingerprint = JSON.stringify({
                    stage: data?.stage,
                    completion: data?.completion_percentage,
                    tip: data?.contextual_tip,
                    steps: (data?.next_steps || []).map((s: any) => ({ t: s?.text, a: s?.action })),
                })
                if (fingerprint && fingerprint !== lastChargedFingerprint.value) {
                    lastChargedFingerprint.value = fingerprint
                    const wordsUsed =
                        countWords(data?.contextual_tip || '') +
                        countWords(data?.stage_label || '') +
                        countWords((data?.next_steps || []).map((s: any) => s?.text).join(' ')) +
                        countWords((data?.writing_milestones || []).map((m: any) => m?.label).join(' '))

                    if (wordsUsed > 0) {
                        recordWordUsage(wordsUsed, 'Manual editor: Progressive guidance', 'chapter')
                            .catch((err) => console.error('Failed to record word usage (progressive guidance):', err))
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load progressive guidance:', error)
        } finally {
            isLoadingGuidance.value = false
        }
    }

    /**
     * Debounced version of requestGuidance (5 seconds)
     */
    const debouncedRequestGuidance = debounce(requestGuidance, 5000)

    /**
     * Toggle step completion
     */
    const toggleStep = (stepId: string) => {
        if (!guidance.value) {
            return
        }

        const step = guidance.value.next_steps.find(s => s.id === stepId)
        if (step) {
            step.completed = !step.completed
            saveCompletedSteps()
            debouncedPersistCompletedSteps()
        }
    }

    /**
     * Save completed steps to localStorage
     */
    const saveCompletedSteps = () => {
        if (!guidance.value) {
            return
        }

        const completedStepIds = guidance.value.next_steps
            .filter(step => step.completed)
            .map(step => step.id)

        setStoredCompletedStepIds(completedStepIds)
    }

    /**
     * Load completed steps from localStorage
     */
    const loadCompletedSteps = () => {
        if (!guidance.value) {
            return
        }

        const storedIds = getStoredCompletedStepIds()

        if (storedIds.length) {
            guidance.value.next_steps.forEach(step => {
                if (storedIds.includes(step.id)) {
                    step.completed = true
                }
            })
        }
    }

    /**
     * Clear all completed steps
     */
    const clearCompletedSteps = () => {
        if (!guidance.value) {
            return
        }

        guidance.value.next_steps.forEach(step => {
            step.completed = false
        })

        clearStoredCompletedStepIds()
        debouncedPersistCompletedSteps()
    }

    /**
     * Get completion percentage for UI
     */
    const getCompletionPercentage = () => {
        return guidance.value?.completion_percentage || 0
    }

    /**
     * Get current stage label
     */
    const getCurrentStageLabel = () => {
        return guidance.value?.stage_label || 'Getting Started'
    }

    return {
        guidance,
        isLoadingGuidance,
        requestGuidance,
        debouncedRequestGuidance,
        toggleStep,
        clearCompletedSteps,
        getCompletionPercentage,
        getCurrentStageLabel,
    }
}
