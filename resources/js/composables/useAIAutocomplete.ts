import { ref, type Ref } from 'vue'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import { route } from 'ziggy-js'

export interface CompletionContext {
  textBefore: string
  textAfter: string
  chapterNumber: number
  chapterTitle: string
  chapterOutline: string
  sectionHeading: string
  sectionOutline: string
  projectTopic: string
}

export function useAIAutocomplete(
  projectSlug: string,
  chapterNumber: number,
  editorRef: Ref<any>,
) {
  const ghostText = ref<string | null>(null)
  const isLoading = ref(false)
  const lastRequestId = ref(0)
  const lastContext = ref<CompletionContext | null>(null)

  const requestCompletion = async (context: CompletionContext): Promise<void> => {
    lastContext.value = context
    const requestId = ++lastRequestId.value

    if (!context.textBefore.trim()) {
      ghostText.value = null
      return
    }

    isLoading.value = true
    try {
      await axios.get('/sanctum/csrf-cookie')
      const response = await axios.post(
        route('api.projects.chapters.autocomplete', {
          project: projectSlug,
          chapter: chapterNumber,
        }),
        {
          text_before: context.textBefore,
          text_after: context.textAfter,
          chapter_number: context.chapterNumber,
          chapter_title: context.chapterTitle,
          chapter_outline: context.chapterOutline,
          section_heading: context.sectionHeading,
          section_outline: context.sectionOutline,
          project_topic: context.projectTopic,
        },
      )

      if (requestId !== lastRequestId.value) return

      const completionRaw = String(response.data?.completion ?? '')
      const completion = completionRaw.replace(/\s+$/g, '')
      ghostText.value = completion || null
    } catch (error) {
      if (requestId !== lastRequestId.value) return
      ghostText.value = null
      throw error
    } finally {
      if (requestId === lastRequestId.value) {
        isLoading.value = false
      }
    }
  }

  const debouncedRequest = useDebounceFn(requestCompletion, 500)

  const acceptSuggestion = (): void => {
    ghostText.value = null
  }

  const dismissSuggestion = (): void => {
    ghostText.value = null
  }

  const triggerManually = async (): Promise<void> => {
    if (!lastContext.value) return
    await requestCompletion(lastContext.value)
  }

  return {
    ghostText,
    isLoading,
    debouncedRequest,
    requestCompletion,
    acceptSuggestion,
    dismissSuggestion,
    triggerManually,
    editorRef, // kept for parity / future use
  }
}
