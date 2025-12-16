import { onMounted, ref, watch, type Ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import type { Chapter } from '@/types'

const stripHtmlToText = (value: string): string => {
  if (!value) return ''

  // Very small "HTML to text" normalization for detecting empty chapters.
  return value
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

const isChapterEmpty = (value: string): boolean => stripHtmlToText(value).length === 0

const stripOuterCodeFence = (value: string) => {
  const trimmed = value.trim()
  const match = trimmed.match(/^```[a-zA-Z0-9_-]*\s*\n([\s\S]*?)\n?```$/)
  return match ? match[1].trim() : value
}

export function useChapterStarter(
  projectSlug: string,
  chapter: Chapter,
  content: Ref<string>,
  options?: {
    canGenerate?: () => boolean
  },
) {
  const starterText = ref<string | null>(null)
  const isGenerating = ref(false)
  const showStarter = ref(false)

  const generateStarter = async (): Promise<void> => {
    if (isGenerating.value) return
    if (options?.canGenerate && !options.canGenerate()) return
    if (!isChapterEmpty(content.value)) return

    isGenerating.value = true
    try {
      await axios.get('/sanctum/csrf-cookie')
      const response = await axios.post(
        route('api.projects.chapters.generate-starter', {
          project: projectSlug,
          chapter: chapter.chapter_number,
        }),
      )

      const starter = stripOuterCodeFence(String(response.data?.starter ?? '')).trim().replace(/^\s*html\s*/i, '')
      starterText.value = starter || null
      showStarter.value = !!starterText.value
    } catch (error) {
      starterText.value = null
      showStarter.value = false
      throw error
    } finally {
      isGenerating.value = false
    }
  }

  const acceptStarter = (): void => {
    showStarter.value = false
    starterText.value = null
  }

  const dismissStarter = (): void => {
    showStarter.value = false
    starterText.value = null
  }

  onMounted(() => {
    if (isChapterEmpty(content.value)) {
      void generateStarter().catch(() => {})
    }
  })

  watch(
    content,
    (newValue) => {
      if (isChapterEmpty(newValue)) return
      showStarter.value = false
      starterText.value = null
    },
    { flush: 'post' },
  )

  return { starterText, isGenerating, showStarter, generateStarter, acceptStarter, dismissStarter }
}
