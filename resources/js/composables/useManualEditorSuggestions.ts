import { ref, watch, type Ref } from 'vue'
import { ContentAnalyzer, type ContentAnalysis } from '@/utils/contentAnalyzer'
import { useDebounceFn } from '@vueuse/core'
import { toast } from 'vue-sonner'
import axios from 'axios'
import { route } from 'ziggy-js'
import { countWords } from '@/utils/wordCount'
import { recordWordUsage } from '@/composables/useWordBalance'
import type { Chapter, UserChapterSuggestion, ChapterContextAnalysis } from '@/types'

export function useManualEditorSuggestions(
  chapter: Chapter,
  projectSlug: string,
  initialSuggestion: UserChapterSuggestion | null,
  editorContent: Ref<string>,
  options?: {
    onUsageRecorded?: () => void | Promise<void>
  },
) {
  const currentSuggestion = ref<UserChapterSuggestion | null>(initialSuggestion)
  const currentAnalysis = ref<ContentAnalysis | null>(null)
  const isAnalyzing = ref(false)
  const chargedSuggestionIds = ref<Set<number>>(new Set<number>())

  /**
   * Analyze content on frontend and send to backend for AI suggestion
   */
  const analyzeAndRequestSuggestion = async () => {
    if (editorContent.value.length < 100) return // Too early
    if (!projectSlug || !chapter?.chapter_number) return

    isAnalyzing.value = true

    try {
      // Frontend analysis
      const analysis = ContentAnalyzer.analyze(editorContent.value)
      currentAnalysis.value = analysis

      // Only request suggestion if issues detected
      if (analysis.detected_issues.length === 0) {
        isAnalyzing.value = false
        return
      }

      // Send to backend for AI suggestion generation
      const response = await axios.post(
        route('projects.manual-editor.analyze', {
          project: projectSlug,
          chapter: chapter.chapter_number,
        }),
        { analysis },
      )

      if (response.data.suggestion) {
        currentSuggestion.value = response.data.suggestion

        const suggestionId = Number(response.data.suggestion?.id)
        if (suggestionId && !chargedSuggestionIds.value.has(suggestionId)) {
          const wordsUsed = countWords(response.data.suggestion?.suggestion_content || '')
          if (wordsUsed > 0) {
            chargedSuggestionIds.value.add(suggestionId)
            recordWordUsage(wordsUsed, 'Manual editor: Smart suggestion', 'chapter', chapter.id)
              .then(() => options?.onUsageRecorded?.())
              .catch((err) => console.error('Failed to record word usage (manual suggestion):', err))
          }
        }
      }
    } catch (error) {
      console.error('Failed to analyze and request suggestion:', error)
    } finally {
      isAnalyzing.value = false
    }
  }

  // Debounced analysis (triggers 5 seconds after user stops typing)
  const debouncedAnalyze = useDebounceFn(analyzeAndRequestSuggestion, 5000)

  // Watch editor content
  watch(editorContent, () => {
    // Always update current analysis for display
    if (editorContent.value.length > 0) {
      currentAnalysis.value = ContentAnalyzer.analyze(editorContent.value)
    }

    // Debounce the backend request
    debouncedAnalyze()
  })

  const saveSuggestion = async () => {
    if (!currentSuggestion.value) return
    if (!projectSlug || !chapter?.chapter_number) return

    try {
      await axios.post(
        route('projects.manual-editor.suggestion.save', {
          project: projectSlug,
          chapter: chapter.chapter_number,
          suggestion: currentSuggestion.value.id,
        }),
      )

      toast('Suggestion saved', {
        description: 'You can refer to this suggestion later.',
      })
    } catch (error) {
      toast('Failed to save suggestion', {
        description: 'Please try again.',
      })
    }
  }

  const clearSuggestion = async () => {
    if (!currentSuggestion.value) return
    if (!projectSlug || !chapter?.chapter_number) return

    try {
      await axios.post(
        route('projects.manual-editor.suggestion.clear', {
          project: projectSlug,
          chapter: chapter.chapter_number,
          suggestion: currentSuggestion.value.id,
        }),
      )

      currentSuggestion.value = null
    } catch (error) {
      toast('Failed to clear suggestion', {
        description: 'Please try again.',
      })
    }
  }

  const applySuggestion = async () => {
    if (!currentSuggestion.value) return
    if (!projectSlug || !chapter?.chapter_number) return

    try {
      await axios.post(
        route('projects.manual-editor.suggestion.apply', {
          project: projectSlug,
          chapter: chapter.chapter_number,
          suggestion: currentSuggestion.value.id,
        }),
      )

      toast('Suggestion applied', {
        description: 'The suggestion has been marked as applied.',
      })

      currentSuggestion.value = null
    } catch (error) {
      toast('Failed to apply suggestion', {
        description: 'Please try again.',
      })
    }
  }

  return {
    currentSuggestion,
    currentAnalysis,
    isAnalyzing,
    saveSuggestion,
    clearSuggestion,
    applySuggestion,
  }
}
