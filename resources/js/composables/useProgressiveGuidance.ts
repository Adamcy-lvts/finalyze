import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'

export interface ProgressiveStep {
    id: string
    text: string
    completed: boolean
}

export interface WritingMilestone {
    id: string
    label: string
    completed: boolean
}

export interface ProgressiveGuidanceData {
    stage: string
    stage_label: string
    completion_percentage: number
    next_steps: ProgressiveStep[]
    contextual_tip: string | null
    writing_milestones: WritingMilestone[]
}

export function useProgressiveGuidance(projectSlug: string, chapterNumber: number) {
    const guidance = ref<ProgressiveGuidanceData | null>(null)
    const isLoadingGuidance = ref(false)
    const lastRequestTime = ref<number>(0)

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
                            content: content,
                        },
                    }),
                }
            )

            if (response.ok) {
                const data = await response.json()
                guidance.value = data

                // Load completed steps from localStorage
                loadCompletedSteps()
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

        const storageKey = `progressive-guidance-${projectSlug}-${chapterNumber}`
        localStorage.setItem(storageKey, JSON.stringify(completedStepIds))
    }

    /**
     * Load completed steps from localStorage
     */
    const loadCompletedSteps = () => {
        if (!guidance.value) {
            return
        }

        const storageKey = `progressive-guidance-${projectSlug}-${chapterNumber}`
        const stored = localStorage.getItem(storageKey)

        if (stored) {
            try {
                const completedStepIds: string[] = JSON.parse(stored)
                guidance.value.next_steps.forEach(step => {
                    if (completedStepIds.includes(step.id)) {
                        step.completed = true
                    }
                })
            } catch (e) {
                console.error('Failed to parse completed steps:', e)
            }
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

        const storageKey = `progressive-guidance-${projectSlug}-${chapterNumber}`
        localStorage.removeItem(storageKey)
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
