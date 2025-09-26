<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <BeakerIcon class="h-5 w-5 text-blue-600 dark:text-blue-400" />
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
          Data Collection Assistant
        </h3>
      </div>
      <button
        v-if="!hasDataCollectionNeeds"
        @click="analyzeChapter(chapterId)"
        :disabled="isLoading"
        class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 disabled:opacity-50 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/30"
      >
        {{ isLoading ? 'Analyzing...' : 'Scan for Data Needs' }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading && !hasDataCollectionNeeds" class="text-center py-4">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
      <p class="text-xs text-gray-500 mt-2">Analyzing chapter content...</p>
    </div>

    <!-- No Data Collection Needs -->
    <div v-else-if="!hasDataCollectionNeeds && !isLoading" class="text-center py-4">
      <DocumentTextIcon class="h-8 w-8 text-gray-400 mx-auto mb-2" />
      <p class="text-sm text-gray-500 dark:text-gray-400">
        No data collection requirements detected in this chapter.
      </p>
      <p class="text-xs text-gray-400 mt-1">
        Add keywords like "survey", "experiment", or "statistical analysis" to get assistance.
      </p>
    </div>

    <!-- Data Collection Detected -->
    <div v-else-if="hasDataCollectionNeeds">
      <!-- Detection Summary -->
      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
        <div class="flex items-start gap-2">
          <ExclamationTriangleIcon class="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">
              Data Collection Requirements Detected
            </p>
            <div class="mt-2 space-y-1">
              <div v-for="type in detectedTypes" :key="type" class="flex items-center justify-between">
                <span class="text-xs text-blue-700 dark:text-blue-300">
                  {{ getTypeName(type) }}
                </span>
                <span
                  class="text-xs font-medium"
                  :class="getConfidenceColor(placeholder?.allConfidence?.[type] || 0)"
                >
                  {{ placeholder?.allConfidence?.[type]?.toFixed(1) }}% confidence
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Primary Template Recommendation -->
      <div v-if="primaryType && placeholder?.template" class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
            Recommended Template
          </h4>
          <span class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded dark:bg-green-900/20 dark:text-green-400">
            {{ getTypeName(primaryType) }}
          </span>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
          {{ placeholder.template.title }}
        </p>

        <div class="flex gap-2">
          <button
            @click="insertTemplate(chapterId, primaryType, 'append')"
            :disabled="isLoading"
            class="flex-1 text-xs px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {{ isLoading ? 'Inserting...' : 'Add Template' }}
          </button>
          <button
            @click="showTemplatePreview = !showTemplatePreview"
            class="text-xs px-2 py-1.5 border border-gray-200 text-gray-600 rounded hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800"
          >
            {{ showTemplatePreview ? 'Hide' : 'Preview' }}
          </button>
        </div>

        <!-- Template Preview -->
        <div v-if="showTemplatePreview" class="mt-3 p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs">
          <pre class="whitespace-pre-wrap text-gray-700 dark:text-gray-300 overflow-auto max-h-32">{{ placeholder.template.content.trim() }}</pre>
        </div>
      </div>

      <!-- Improvement Suggestions -->
      <div v-if="suggestions.length > 0" class="space-y-2">
        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
          Research Guidelines
        </h4>
        <div v-for="suggestion in suggestions" :key="suggestion.type" class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
          <h5 class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ suggestion.title }}
          </h5>
          <ul class="space-y-1">
            <li
              v-for="(tip, index) in suggestion.suggestions"
              :key="index"
              class="flex items-start gap-1.5 text-xs text-gray-600 dark:text-gray-400"
            >
              <CheckIcon class="h-3 w-3 text-green-500 mt-0.5 flex-shrink-0" />
              <span>{{ tip }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- All Templates Option -->
      <details class="border border-gray-200 dark:border-gray-700 rounded-lg">
        <summary class="p-3 cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
          Browse All Templates
        </summary>
        <div class="p-3 pt-0 space-y-2">
          <div
            v-for="(template, type) in templates"
            :key="type"
            class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded"
          >
            <div>
              <span class="text-xs font-medium text-gray-900 dark:text-gray-100">
                {{ getTypeName(type) }}
              </span>
              <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ template.title }}
              </p>
            </div>
            <button
              @click="insertTemplate(chapterId, type, 'append')"
              :disabled="isLoading"
              class="text-xs px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              Add
            </button>
          </div>
        </div>
      </details>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
      <div class="flex items-start gap-2">
        <ExclamationTriangleIcon class="h-4 w-4 text-red-500 mt-0.5 flex-shrink-0" />
        <div>
          <p class="text-sm font-medium text-red-800 dark:text-red-200">
            Analysis Error
          </p>
          <p class="text-xs text-red-600 dark:text-red-400 mt-1">
            {{ error }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import {
  BeakerIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'
import { useDataCollection } from '@/composables/useDataCollection'
import { toast } from 'vue-sonner'

interface Props {
  chapterId: number
  content?: string
}

const props = defineProps<Props>()

const showTemplatePreview = ref(false)

const {
  isLoading,
  error,
  detection,
  placeholder,
  suggestions,
  templates,
  hasDataCollectionNeeds,
  detectedTypes,
  primaryType,
  primaryConfidence,
  analyzeChapter,
  insertTemplate: insertTemplateAction,
  getAllTemplates,
  getTypeName,
  getConfidenceLevel,
  getConfidenceColor,
  clearData
} = useDataCollection()

// Auto-analyze when content changes significantly
let analysisTimeout: NodeJS.Timeout | null = null
watch(() => props.content, (newContent, oldContent) => {
  if (!newContent || newContent === oldContent) return

  // Clear existing timeout
  if (analysisTimeout) {
    clearTimeout(analysisTimeout)
  }

  // Analyze after 3 seconds of no changes
  analysisTimeout = setTimeout(() => {
    if (newContent && newContent.length > 500) {
      analyzeChapter(props.chapterId)
    }
  }, 3000)
}, { immediate: false })

async function insertTemplate(chapterId: number, type: string, position: 'append' | 'prepend' | 'replace' = 'append') {
  const success = await insertTemplateAction(chapterId, type, position)
  if (success) {
    toast.success('Template added successfully')
    showTemplatePreview.value = false
    // Re-analyze after template insertion
    setTimeout(() => {
      analyzeChapter(chapterId)
    }, 1000)
  } else {
    toast.error(error.value || 'Failed to insert template')
  }
}

onMounted(async () => {
  // Load all templates
  await getAllTemplates()

  // Auto-analyze if there's content
  if (props.content && props.content.length > 500) {
    await analyzeChapter(props.chapterId)
  }
})
</script>