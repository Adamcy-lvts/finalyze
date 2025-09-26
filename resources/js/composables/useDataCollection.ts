import { ref, computed } from 'vue'
import type { Ref } from 'vue'

interface DataCollectionDetection {
  types: string[]
  confidence: Record<string, number>
  hasDataCollectionNeeds: boolean
}

interface DataCollectionTemplate {
  title: string
  content: string
}

interface DataCollectionPlaceholder {
  hasPlaceholder: boolean
  detectedType?: string
  confidence?: number
  template?: DataCollectionTemplate
  allDetected?: string[]
  allConfidence?: Record<string, number>
  message?: string
}

interface DataCollectionSuggestion {
  type: string
  title: string
  suggestions: string[]
}

export function useDataCollection() {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const detection = ref<DataCollectionDetection | null>(null)
  const placeholder = ref<DataCollectionPlaceholder | null>(null)
  const suggestions = ref<DataCollectionSuggestion[]>([])
  const templates = ref<Record<string, DataCollectionTemplate>>({})

  const hasDataCollectionNeeds = computed(() =>
    detection.value?.hasDataCollectionNeeds ?? false
  )

  const detectedTypes = computed(() =>
    detection.value?.types ?? []
  )

  const primaryType = computed(() => {
    if (!placeholder.value?.hasPlaceholder) return null
    return placeholder.value.detectedType
  })

  const primaryConfidence = computed(() => {
    if (!placeholder.value?.hasPlaceholder) return 0
    return placeholder.value.confidence ?? 0
  })

  /**
   * Detect data collection needs for a chapter
   */
  async function detectDataCollectionNeeds(chapterId: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch(`/api/chapters/${chapterId}/detect`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      if (!response.ok) {
        throw new Error('Failed to detect data collection needs')
      }

      const data = await response.json()
      detection.value = data.detection
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error detecting data collection needs:', err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Generate placeholder content for a chapter
   */
  async function generatePlaceholder(chapterId: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch(`/api/chapters/${chapterId}/placeholder`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      if (!response.ok) {
        throw new Error('Failed to generate placeholder')
      }

      const data = await response.json()
      placeholder.value = data.placeholder
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error generating placeholder:', err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Get improvement suggestions for a chapter
   */
  async function getSuggestions(chapterId: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch(`/api/chapters/${chapterId}/suggestions`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      if (!response.ok) {
        throw new Error('Failed to get suggestions')
      }

      const data = await response.json()
      suggestions.value = data.suggestions
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error getting suggestions:', err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Get all available templates
   */
  async function getAllTemplates(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch('/api/data-collection/templates', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      if (!response.ok) {
        throw new Error('Failed to get templates')
      }

      const data = await response.json()
      templates.value = data.templates
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error getting templates:', err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Get specific template by type
   */
  async function getTemplate(type: string): Promise<DataCollectionTemplate | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch(`/api/data-collection/template?type=${encodeURIComponent(type)}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      if (!response.ok) {
        throw new Error('Failed to get template')
      }

      const data = await response.json()
      return data.template
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error getting template:', err)
      return null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Insert template into chapter
   */
  async function insertTemplate(
    chapterId: number,
    type: string,
    position: 'append' | 'prepend' | 'replace' = 'append'
  ): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const response = await fetch(`/api/chapters/${chapterId}/insert-template`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ type, position })
      })

      if (!response.ok) {
        throw new Error('Failed to insert template')
      }

      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Error inserting template:', err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Analyze chapter for data collection needs (combined detection and suggestions)
   */
  async function analyzeChapter(chapterId: number): Promise<void> {
    await Promise.all([
      detectDataCollectionNeeds(chapterId),
      generatePlaceholder(chapterId),
      getSuggestions(chapterId)
    ])
  }

  /**
   * Get human-readable type name
   */
  function getTypeName(type: string): string {
    const typeNames: Record<string, string> = {
      survey: 'Survey/Questionnaire',
      experiment: 'Laboratory Experiment',
      engineering: 'Engineering Design',
      statistical: 'Statistical Analysis',
      construction: 'Construction Project'
    }
    return typeNames[type] || type.charAt(0).toUpperCase() + type.slice(1)
  }

  /**
   * Get confidence level description
   */
  function getConfidenceLevel(confidence: number): string {
    if (confidence >= 50) return 'High'
    if (confidence >= 25) return 'Medium'
    return 'Low'
  }

  /**
   * Get confidence color class
   */
  function getConfidenceColor(confidence: number): string {
    if (confidence >= 50) return 'text-green-600 dark:text-green-400'
    if (confidence >= 25) return 'text-yellow-600 dark:text-yellow-400'
    return 'text-red-600 dark:text-red-400'
  }

  /**
   * Clear all data
   */
  function clearData(): void {
    detection.value = null
    placeholder.value = null
    suggestions.value = []
    error.value = null
  }

  return {
    // State
    isLoading,
    error,
    detection,
    placeholder,
    suggestions,
    templates,

    // Computed
    hasDataCollectionNeeds,
    detectedTypes,
    primaryType,
    primaryConfidence,

    // Methods
    detectDataCollectionNeeds,
    generatePlaceholder,
    getSuggestions,
    getAllTemplates,
    getTemplate,
    insertTemplate,
    analyzeChapter,
    getTypeName,
    getConfidenceLevel,
    getConfidenceColor,
    clearData
  }
}