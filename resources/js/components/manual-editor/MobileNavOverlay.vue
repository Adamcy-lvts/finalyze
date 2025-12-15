<!-- /resources/js/components/manual-editor/MobileNavOverlay.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { X } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import ChapterNavigation from '@/components/chapter-editor/ChapterNavigation.vue'
import SmartSuggestionPanel from '@/components/manual-editor/SmartSuggestionPanel.vue'
import QuickActionsPanel from '@/components/manual-editor/QuickActionsPanel.vue'
import DefensePreparationPanel from '@/components/chapter-editor/DefensePreparationPanel.vue'
import CitationHelper from '@/components/chapter-editor/CitationHelper.vue'
import { Separator } from '@/components/ui/separator'
import type { Chapter, Project, UserChapterSuggestion, ChapterContextAnalysis } from '@/types'

interface Props {
  showLeftSidebar: boolean
  showRightSidebar: boolean
  isMobile: boolean
  allChapters: Chapter[]
  currentChapter: Chapter
  project: Project
  facultyChapters: any[]
  currentWordCount: number
  targetWordCount: number
  chapterContentLength: number
  selectedText: string
  isAnalyzing: boolean
  isSaving: boolean
  currentSuggestion: UserChapterSuggestion | null
  currentAnalysis: ChapterContextAnalysis | null
  chapterContent: string
  showCitationHelper: boolean
  ensureBalance?: (requiredWords: number, action: string) => boolean
  onUsage?: (wordsUsed: number, description: string) => void | Promise<void>
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:showLeftSidebar': [value: boolean]
  'update:showRightSidebar': [value: boolean]
  'update:showCitationHelper': [value: boolean]
  goToChapter: [chapterNumber: number]
  generateNextChapter: []
  deleteChapter: [chapterId: number]
  saveSuggestion: [suggestion: string]
  clearSuggestion: []
  applySuggestion: []
  textImproved: [text: string]
  textExpanded: [text: string]
  citationsSuggested: [suggestions: string]
  textRephrased: [alternatives: string]
  insertCitation: [citation: string]
}>()

// Local state
const localShowDefensePrep = ref(true)
const localShowCitationHelper = ref(props.showCitationHelper)

// Watch for prop changes and update local state
watch(() => props.showCitationHelper, (newVal) => {
  localShowCitationHelper.value = newVal
})

// Methods
const handleCloseLeftSidebar = () => emit('update:showLeftSidebar', false)
const handleCloseRightSidebar = () => emit('update:showRightSidebar', false)
const handleUpdateShowCitationHelper = (value: boolean) => {
  localShowCitationHelper.value = value
  emit('update:showCitationHelper', value)
}
const handleGoToChapter = (chapterNumber: number) => {
  emit('goToChapter', chapterNumber)
  handleCloseLeftSidebar()
}
const handleGenerateNextChapter = () => emit('generateNextChapter')
const handleDeleteChapter = (chapterId: number) => emit('deleteChapter', chapterId)
const handleInsertCitation = (citation: string) => emit('insertCitation', citation)
</script>

<template>
  <div>
    <!-- Mobile Left Sidebar Overlay -->
    <Transition
      enter-active-class="duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="showLeftSidebar && isMobile" class="fixed inset-0 z-50 lg:hidden">
        <Transition
          enter-active-class="duration-300 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="duration-200 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div class="fixed inset-0 bg-black/50" @click="handleCloseLeftSidebar"></div>
        </Transition>
        <Transition
          enter-active-class="duration-300 ease-out"
          enter-from-class="-translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="duration-200 ease-in"
          leave-from-class="translate-x-0"
          leave-to-class="-translate-x-full"
        >
          <div class="fixed top-0 left-0 h-full w-80 overflow-y-auto border-r bg-background shadow-xl transform transition-transform">
            <div class="border-b p-3">
              <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Table of Contents</h2>
                <Button @click="handleCloseLeftSidebar" variant="ghost" size="sm" class="h-8 w-8">
                  <X class="h-4 w-4" />
                </Button>
              </div>
            </div>
            <div class="space-y-4 p-4 mobile-enhanced">
              <!-- Chapter Navigation -->
              <ChapterNavigation
                :all-chapters="allChapters"
                :current-chapter="currentChapter"
                :project="project"
                :outlines="project.outlines || []"
                :faculty-chapters="facultyChapters || []"
                :current-word-count="currentWordCount"
                :target-word-count="targetWordCount"
                :writing-quality-score="0"
                :chapter-content-length="chapterContentLength"
                @go-to-chapter="handleGoToChapter"
                @generate-next-chapter="handleGenerateNextChapter"
                @delete-chapter="handleDeleteChapter"
              />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Mobile Right Sidebar Overlay -->
    <Transition
      enter-active-class="duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="showRightSidebar && isMobile" class="fixed inset-0 z-50 lg:hidden">
        <Transition
          enter-active-class="duration-300 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="duration-200 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div class="fixed inset-0 bg-black/50" @click="handleCloseRightSidebar"></div>
        </Transition>
        <Transition
          enter-active-class="duration-300 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="duration-200 ease-in"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
        >
          <div class="fixed top-0 right-0 h-full w-96 overflow-y-auto border-l bg-background shadow-xl transform transition-transform">
            <div class="border-b p-3">
              <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">AI Tools</h2>
                <Button @click="handleCloseRightSidebar" variant="ghost" size="sm" class="h-8 w-8">
                  <X class="h-4 w-4" />
                </Button>
              </div>
            </div>
            <div class="space-y-4 p-4 mobile-enhanced">
              <!-- Smart Suggestion Panel (shown when suggestion exists) -->
              <div v-if="currentSuggestion" class="space-y-2">
                <SmartSuggestionPanel
                  :suggestion="currentSuggestion"
                  :analysis="currentAnalysis"
                  @save="$emit('saveSuggestion', $event)"
                  @clear="$emit('clearSuggestion')"
                  @apply="$emit('applySuggestion')"
                />
                <Separator class="bg-border/50" />
              </div>

              <!-- Quick Actions Panel -->
              <QuickActionsPanel
                :project-slug="project.slug"
                :chapter-number="currentChapter.chapter_number"
                :selected-text="selectedText"
                :chapter-content="chapterContent"
                :is-processing="isAnalyzing || isSaving"
                :ensure-balance="ensureBalance"
                :on-usage="onUsage"
                @text-improved="$emit('textImproved', $event)"
                @text-expanded="$emit('textExpanded', $event)"
                @citations-suggested="$emit('citationsSuggested', $event)"
                @text-rephrased="$emit('textRephrased', $event)"
              />

              <Separator class="bg-border/50" />

              <!-- Citation Helper -->
              <CitationHelper
                v-model:show-citation-helper="localShowCitationHelper"
                :chapter-content="chapterContent"
                @insert-citation="handleInsertCitation"
                @update:show-citation-helper="handleUpdateShowCitationHelper"
              />

              <Separator class="bg-border/50" />

              <!-- Defense Preparation Panel -->
              <DefensePreparationPanel
                v-model:show-defense-prep="localShowDefensePrep"
                :questions="[]"
                :is-loading="false"
                :is-generating="false"
                :chapter-context="{
                  project: project,
                  chapter: currentChapter,
                  wordCount: currentWordCount,
                }"
                @generate-more="() => {}"
                @refresh="() => {}"
              />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Mobile-specific enhancements for compact layout */
.mobile-enhanced {
  /* Compact design for better space utilization */
}

/* Make buttons more compact but still touchable */
.mobile-enhanced :deep(button) {
  min-height: 36px;
  padding: 0.5rem 0.75rem;
  font-size: 0.825rem;
}

/* Reduce text sizes to fit more content */
.mobile-enhanced :deep(.text-xs) {
  font-size: 0.7rem;
}

.mobile-enhanced :deep(.text-sm) {
  font-size: 0.8rem;
}

.mobile-enhanced :deep(.text-base) {
  font-size: 0.9rem;
}

/* Reduce spacing for more compact layout */
.mobile-enhanced :deep(.gap-2) {
  gap: 0.375rem;
}

.mobile-enhanced :deep(.gap-3) {
  gap: 0.5rem;
}

.mobile-enhanced :deep(.p-2) {
  padding: 0.375rem;
}

.mobile-enhanced :deep(.p-3) {
  padding: 0.5rem;
}

.mobile-enhanced :deep(.p-4) {
  padding: 0.625rem;
}

/* Smaller icons to save space */
.mobile-enhanced :deep(.h-3) {
  height: 0.75rem;
  width: 0.75rem;
}

.mobile-enhanced :deep(.h-4) {
  height: 0.875rem;
  width: 0.875rem;
}

/* Make badges more compact */
.mobile-enhanced :deep(.badge) {
  padding: 0.25rem 0.5rem;
  font-size: 0.7rem;
  line-height: 1.2;
}

/* Reduce card padding for mobile */
.mobile-enhanced :deep(.card) {
  padding: 0.75rem;
}

.mobile-enhanced :deep(.card-header) {
  padding: 0.75rem;
  padding-bottom: 0.5rem;
}

.mobile-enhanced :deep(.card-content) {
  padding: 0.75rem;
  padding-top: 0;
}

/* Make collapsible triggers more compact */
.mobile-enhanced :deep(.collapsible-trigger) {
  min-height: 40px;
  padding: 0.75rem;
}

/* Reduce spacing in mobile for tighter layout */
.mobile-enhanced :deep(.space-y-1 > * + *) {
  margin-top: 0.25rem;
}

.mobile-enhanced :deep(.space-y-2 > * + *) {
  margin-top: 0.375rem;
}

.mobile-enhanced :deep(.space-y-3 > * + *) {
  margin-top: 0.5rem;
}

.mobile-enhanced :deep(.space-y-4 > * + *) {
  margin-top: 0.75rem;
}

/* Make form elements more compact */
.mobile-enhanced :deep(input),
.mobile-enhanced :deep(select),
.mobile-enhanced :deep(textarea) {
  padding: 0.5rem;
  font-size: 0.875rem;
}

/* Compact grid layouts */
.mobile-enhanced :deep(.grid-cols-2) {
  gap: 0.375rem;
}

/* Reduce alert padding */
.mobile-enhanced :deep(.alert) {
  padding: 0.625rem;
}

/* Tighter line heights for better space utilization */
.mobile-enhanced :deep(*) {
  line-height: 1.4;
}
</style>
