import { ref, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import axios from 'axios'
import { route } from 'ziggy-js'
import type { Chapter } from '@/types'

export function useManualEditor(chapter: Chapter, projectSlug: string) {
  const content = ref(chapter.content || '')
  const isDirty = ref(false)
  const isSaving = ref(false)
  const lastSaved = ref<string | null>(null)

  /**
   * Update content and mark as dirty
   */
  const updateContent = (newContent: string) => {
    content.value = newContent
    isDirty.value = true
    debouncedSave()
  }

  /**
   * Save chapter content
   */
  const save = async () => {
    if (!isDirty.value || isSaving.value) return

    isSaving.value = true

    try {
      await axios.post(
        route('projects.manual-editor.save', {
          project: projectSlug,
          chapter: chapter.chapter_number,
        }),
        {
          content: content.value,
        },
      )

      isDirty.value = false
      lastSaved.value = new Date().toLocaleTimeString()
    } catch (error) {
      console.error('Failed to save chapter:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Debounced save (triggers 2 seconds after user stops typing)
   */
  const debouncedSave = useDebounceFn(save, 2000)

  /**
   * Manual save (can be called directly from UI)
   */
  const manualSave = async () => {
    // Force save even if not dirty (for manual save button)
    if (isSaving.value) return

    isSaving.value = true

    try {
      await axios.post(
        route('projects.manual-editor.save', {
          project: projectSlug,
          chapter: chapter.chapter_number,
        }),
        {
          content: content.value,
        },
      )

      isDirty.value = false
      lastSaved.value = new Date().toLocaleTimeString()
      return true
    } catch (error) {
      console.error('Failed to save chapter:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Warn user before leaving if there are unsaved changes
   */
  const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (isDirty.value) {
      e.preventDefault()
      e.returnValue = ''
    }
  }

  /**
   * Intercept Inertia navigation if there are unsaved changes
   */
  const handleInertiaNavigate = (event: any) => {
    if (isDirty.value && !confirm('You have unsaved changes. Are you sure you want to leave?')) {
      event.preventDefault()
    }
  }

  // Set up navigation guards
  window.addEventListener('beforeunload', handleBeforeUnload)
  const removeInertiaListener = router.on('before', handleInertiaNavigate)

  // Clean up on unmount
  onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    removeInertiaListener()

    // Save any pending changes before leaving
    if (isDirty.value) {
      save()
    }
  })

  return {
    content,
    isDirty,
    isSaving,
    lastSaved,
    updateContent,
    save,
    manualSave,
  }
}
