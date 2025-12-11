<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue-sonner'
import { MessageSquare, Loader2, PanelRightClose, PanelRightOpen, PanelLeftClose, PanelLeftOpen, ChevronLeft, ChevronRight, ArrowLeft, Menu, Save, Maximize2, Minimize2, CheckCircle, Brain, Moon, Sun } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Progress } from '@/components/ui/progress'
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue'
import SmartSuggestionPanel from '@/components/manual-editor/SmartSuggestionPanel.vue'
import ProgressiveGuidancePanel from '@/components/manual-editor/ProgressiveGuidancePanel.vue'
import QuickActionsPanel from '@/components/manual-editor/QuickActionsPanel.vue'
import MobileNavOverlay from '@/components/manual-editor/MobileNavOverlay.vue'
import DefensePreparationPanel from '@/components/chapter-editor/DefensePreparationPanel.vue'
import ChapterNavigation from '@/components/chapter-editor/ChapterNavigation.vue'
import ExportMenu from '@/components/chapter-editor/ExportMenu.vue'
import SafeHtmlText from '@/components/SafeHtmlText.vue'
import { Toaster } from '@/components/ui/sonner'
import CitationHelper from '@/components/chapter-editor/CitationHelper.vue'
import { useManualEditor } from '@/composables/useManualEditor'
import { useManualEditorSuggestions } from '@/composables/useManualEditorSuggestions'
import { useProgressiveGuidance } from '@/composables/useProgressiveGuidance'
import { useTextHistory } from '@/composables/useTextHistory'
import ChatModeLayout from '@/components/chapter-editor/ChatModeLayout.vue'
import { useAppearance } from '@/composables/useAppearance'
import type { Project, Chapter, UserChapterSuggestion, ChapterContextAnalysis } from '@/types'

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  timestamp: Date
}

const props = defineProps<{
  project: Project
  chapter: Chapter
  allChapters: Chapter[]
  facultyChapters: any[]
  currentSuggestion: UserChapterSuggestion | null
  contextAnalysis: ChapterContextAnalysis | null
  chatHistory: ChatMessage[]
}>()


// Get page instance for flash messages
const page = usePage()

// Watch for flash messages and show toasts
// Watch for flash messages and show toasts
watch(
  () => page.props?.flash,
  (flash: any) => {
    if (!flash) return
    console.log('🔔 Flash message received:', flash)
    if (flash.success) {
      console.log('✅ Showing success toast:', flash.success)
      toast.success(flash.success)
    }
    if (flash.error) {
      console.log('❌ Showing error toast:', flash.error)
      toast.error(flash.error)
    }
  },
  { deep: true, immediate: true }
)

const { content, isDirty, isSaving, lastSaved, updateContent, manualSave } = useManualEditor(props.chapter, props.project.slug)

const { currentSuggestion, currentAnalysis, isAnalyzing, saveSuggestion, clearSuggestion, applySuggestion } =
  useManualEditorSuggestions(props.chapter, props.currentSuggestion, content)

const { contentHistory, historyIndex, addToHistory, undo, redo, canUndo, canRedo } = useTextHistory(props.chapter.content || '');

// Chat Mode state
const showChatMode = ref(false)
const chapterTitle = ref(props.chapter.title || '')
const showPreview = ref(false)
const isGenerating = ref(false)
const generationProgress = ref('')

const toggleChatMode = () => {
  showChatMode.value = !showChatMode.value
}

const handleUndo = () => {
  const undoContent = undo();
  if (undoContent !== null) {
    updateContent(undoContent);
  }
};

const handleRedo = () => {
  const redoContent = redo();
  if (redoContent !== null) {
    updateContent(redoContent);
  }
};

const handleContentUpdate = (newContent: string) => {
  updateContent(newContent);
  addToHistory(newContent);
};

const isValid = computed(() => {
  const title = chapterTitle.value?.trim();
  const c = content.value?.trim();
  return !!(title && title.length > 0 && c && c.length > 50);
});

const {
  guidance,
  isLoadingGuidance,
  debouncedRequestGuidance,
  toggleStep,
} = useProgressiveGuidance(props.project.slug, props.chapter.chapter_number)

// Mobile responsive state (check early)
const getInitialMobileState = () => typeof window !== 'undefined' && window.innerWidth < 1024

// Load sidebar state from localStorage
const loadSidebarState = (key: string, defaultValue: boolean) => {
  try {
    const stored = localStorage.getItem(key)
    return stored !== null ? stored === 'true' : defaultValue
  } catch {
    return defaultValue
  }
}

// Sidebar visibility - initialize based on stored state or default
const isMobile = ref(getInitialMobileState())
const showLeftSidebar = ref(loadSidebarState('manualEditor_showLeftSidebar', !isMobile.value))
const showRightSidebar = ref(loadSidebarState('manualEditor_showRightSidebar', !isMobile.value))
const selectedText = ref('')

// Defense preparation state
const showDefensePrep = ref(false)
const defenseQuestions = ref([])
const isGeneratingDefense = ref(false)

// Citation Helper state
const showCitationHelper = ref(true)

// Fullscreen state
const isNativeFullscreen = ref(false)

const enterNativeFullscreen = async () => {
  try {
    const element = document.documentElement
    if (element.requestFullscreen) {
      await element.requestFullscreen()
    } else if ((element as any).webkitRequestFullscreen) {
      await (element as any).webkitRequestFullscreen()
    } else if ((element as any).msRequestFullscreen) {
      await (element as any).msRequestFullscreen()
    }
  } catch (error) {
    console.error('Error entering fullscreen:', error)
    toast.error('Fullscreen Error', { description: 'Unable to enter fullscreen mode' })
  }
}

const exitNativeFullscreen = async () => {
  try {
    if (document.exitFullscreen) {
      await document.exitFullscreen()
    } else if ((document as any).webkitExitFullscreen) {
      await (document as any).webkitExitFullscreen()
    } else if ((document as any).msExitFullscreen) {
      await (document as any).msExitFullscreen()
    }
  } catch (error) {
    console.error('Error exiting fullscreen:', error)
    toast.error('Fullscreen Error', { description: 'Unable to exit fullscreen mode' })
  }
}

const toggleNativeFullscreen = async () => {
  if (isNativeFullscreen.value) {
    await exitNativeFullscreen()
  } else {
    await enterNativeFullscreen()
  }
}

const handleFullscreenChange = () => {
  const fullscreenElement = document.fullscreenElement || (document as any).webkitFullscreenElement || (document as any).msFullscreenElement
  isNativeFullscreen.value = !!fullscreenElement
}

const goToBulkAnalysis = () => {
  router.visit(route('projects.analysis', { project: props.project.slug }))
}

onMounted(() => {
  document.addEventListener('fullscreenchange', handleFullscreenChange)
  document.addEventListener('webkitfullscreenchange', handleFullscreenChange)
  document.addEventListener('msfullscreenchange', handleFullscreenChange)
})

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', handleFullscreenChange)
  document.removeEventListener('webkitfullscreenchange', handleFullscreenChange)
  document.removeEventListener('msfullscreenchange', handleFullscreenChange)
})

// Independent Dark Mode Logic - Local Class Strategy
const { isDark: globalIsDark } = useAppearance();
const isEditorDark = ref(false);

// Initialize editor-specific theme
const initEditorTheme = () => {
  // Check local preference
  const saved = localStorage.getItem(`manual_editor_theme_${props.project.id}`);
  if (saved) {
    isEditorDark.value = saved === 'dark';
  } else {
    // Default to global preference
    isEditorDark.value = globalIsDark.value;
  }
};

const toggleEditorTheme = () => {
  isEditorDark.value = !isEditorDark.value;
  const newMode = isEditorDark.value ? 'dark' : 'light';
  localStorage.setItem(`manual_editor_theme_${props.project.id}`, newMode);
  toast.success(`Switched to ${isEditorDark.value ? 'Dark' : 'Light'} Mode`);
};

// Initialize theme on mount
onMounted(() => {
  initEditorTheme();
});

const progressPercentage = computed(() => {
  const current = currentAnalysis.value?.word_count ?? props.chapter.word_count
  const target = props.chapter.target_word_count || 1000 // Default fallback
  return Math.min((current / target) * 100, 100)
})

const saveStatusText = computed(() => {
  if (isSaving.value) return 'Saving...'
  if (lastSaved.value) return `Saved ${lastSaved.value}`
  return 'Not saved'
})

const toggleLeftSidebar = () => {
  showLeftSidebar.value = !showLeftSidebar.value
}

const toggleRightSidebar = () => {
  showRightSidebar.value = !showRightSidebar.value
}



// Watch sidebar state and save to localStorage (only on desktop)
watch(showLeftSidebar, (newValue) => {
  if (!isMobile.value) {
    try {
      localStorage.setItem('manualEditor_showLeftSidebar', String(newValue))
    } catch (error) {
      console.error('Failed to save left sidebar state:', error)
    }
  }
})

watch(showRightSidebar, (newValue) => {
  if (!isMobile.value) {
    try {
      localStorage.setItem('manualEditor_showRightSidebar', String(newValue))
    } catch (error) {
      console.error('Failed to save right sidebar state:', error)
    }
  }
})

// Keyboard shortcut for Ctrl+S to save
const handleKeyboardShortcut = (e: KeyboardEvent) => {
  // Ctrl+S or Cmd+S to save
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault()
    handleManualSave()
  }
}

// Check if mobile on mount and handle window resize
onMounted(() => {
  // Initialize based on current window size
  isMobile.value = window.innerWidth < 1024

  // On desktop, use stored state (already initialized in refs)
  // On mobile, ensure sidebars are hidden
  if (isMobile.value) {
    showLeftSidebar.value = false
    showRightSidebar.value = false
  }

  const checkMobile = () => {
    const wasMobile = isMobile.value
    isMobile.value = window.innerWidth < 1024

    // Only update sidebar visibility when crossing mobile/desktop threshold
    if (wasMobile !== isMobile.value) {
      if (!isMobile.value) {
        // Switched to desktop: restore from localStorage or default to true
        showLeftSidebar.value = loadSidebarState('manualEditor_showLeftSidebar', true)
        showRightSidebar.value = loadSidebarState('manualEditor_showRightSidebar', true)
      } else {
        // Switched to mobile: hide sidebars
        showLeftSidebar.value = false
        showRightSidebar.value = false
      }
    }
  }

  window.addEventListener('resize', checkMobile)
  window.addEventListener('keydown', handleKeyboardShortcut)

  onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    window.removeEventListener('keydown', handleKeyboardShortcut)
  })
})

// Watch for content changes to trigger progressive guidance
watch(
  () => currentAnalysis.value,
  (analysis) => {
    if (analysis) {
      debouncedRequestGuidance(
        {
          word_count: analysis.word_count,
          citation_count: analysis.citation_count,
          table_count: analysis.table_count,
          figure_count: analysis.figure_count,
          claim_count: analysis.claim_count,
          has_introduction: analysis.has_introduction,
          has_conclusion: analysis.has_conclusion,
          detected_issues: analysis.detected_issues,
          quality_metrics: analysis.quality_metrics,
        },
        content.value
      )
    }
  },
  { deep: true }
)

// Handle quick action results (placeholder - would integrate with editor)
const handleTextImproved = (text: string) => {
  console.log('Improved text:', text)
  // TODO: Replace selected text or show in modal
}

const handleTextExpanded = (text: string) => {
  console.log('Expanded text:', text)
  // TODO: Replace selected text or show in modal
}

const handleCitationsSuggested = (suggestions: string) => {
  console.log('Citation suggestions:', suggestions)
  // TODO: Show in modal or panel
}

const handleTextRephrased = (alternatives: string) => {
  console.log('Alternative phrasings:', alternatives)
  // TODO: Show in modal for selection
}

const prevChapter = computed(() => props.allChapters.find(c => c.chapter_number === props.chapter.chapter_number - 1))
const nextChapter = computed(() => props.allChapters.find(c => c.chapter_number === props.chapter.chapter_number + 1))

const handleInsertCitation = (citation: string) => {
  // Append citation to content or insert at cursor if possible
  // Since we don't have direct cursor access easily without Tiptap instance,
  // we'll append it for now or rely on the user copying it.
  // But wait, we can try to append it to the content.
  const newContent = content.value + ` ${citation}`
  updateContent(newContent)
  toast.success('Citation appended to content')
}

// Navigation Methods
const goToChapter = (chapterNumber: number) => {
  if (isDirty.value) {
    if (!confirm('You have unsaved changes. Are you sure you want to switch chapters?')) {
      return
    }
  }

  router.visit(route('projects.manual-editor.show', {
    project: props.project.slug,
    chapter: chapterNumber
  }))
}

const generateNextChapter = () => {
  // In manual mode, this just creates/goes to the next chapter
  const nextNumber = props.chapter.chapter_number + 1
  const nextChapter = props.allChapters.find(c => c.chapter_number === nextNumber)

  if (nextChapter) {
    goToChapter(nextNumber)
  } else {
    // Create new chapter (handled by controller if we just visit)
    // But we need the ID. Since we don't have it, we might need a specific route or logic.
    // For now, let's assume we can't easily create a new one without an ID in manual mode 
    // unless we have a 'create' route. 
    // Actually, ChapterController.edit/show creates it if it doesn't exist.
    // But we need to know the ID to pass to the route.
    // Let's try to find if there's a route that accepts chapter number.
    // The route `projects.manual-editor.show` takes `{project, chapter}` where chapter is ID.
    // We might need to use a different approach or just show a toast for now if it doesn't exist.
    toast.info('Please create the next chapter from the dashboard first.')
  }
}

const deleteChapter = (chapterId: number) => {
  if (!confirm('Are you sure you want to delete this chapter?')) return

  router.delete(route('chapters.destroy', {
    project: props.project.slug,
    chapter: props.allChapters.find(c => c.id === chapterId)?.slug
  }), {
    onSuccess: () => toast.success('Chapter deleted')
  })
}

// Manual save with toast notification
const handleManualSave = async () => {
  try {
    await manualSave()
    toast.success('Chapter saved successfully')
  } catch (error) {
    toast.error('Failed to save chapter')
  }
}

// Mark chapter as complete
const markAsComplete = async () => {
  console.log('🔵 [MARK COMPLETE] Function called')
  console.log('🔵 [MARK COMPLETE] Current word count:', currentAnalysis.value?.word_count ?? props.chapter.word_count)

  try {
    // First save the chapter
    console.log('🔵 [MARK COMPLETE] Saving chapter...')
    await manualSave()
    console.log('✅ [MARK COMPLETE] Chapter saved')

    // Then mark as complete (flash messages handled by watcher)
    const routeUrl = route('projects.manual-editor.mark-complete', {
      project: props.project.slug,
      chapter: props.chapter.chapter_number
    })
    console.log('🔵 [MARK COMPLETE] Posting to:', routeUrl)

    router.post(routeUrl, {}, {
      onSuccess: () => {
        console.log('✅ [MARK COMPLETE] Request successful')
      },
      onError: (errors) => {
        console.error('❌ [MARK COMPLETE] Request failed:', errors)
      }
    })
  } catch (error) {
    console.error('❌ [MARK COMPLETE] Error:', error)
    toast.error('Failed to save chapter before marking complete')
  }
}
</script>

<template>

  <Head :title="`${chapter.title} - Manual Editor`" />

  <Transition name="chat-glide">
    <ChatModeLayout v-if="showChatMode" :project="project" :chapter="chapter" :chapter-title="chapterTitle"
      :chapter-content="content" :current-word-count="currentAnalysis?.word_count ?? chapter.word_count"
      :target-word-count="chapter.target_word_count" :progress-percentage="progressPercentage"
      :writing-quality-score="0" :is-valid="isValid" :is-saving="isSaving" :show-preview="showPreview"
      :is-generating="isGenerating" :generation-progress="generationProgress" :history-index="historyIndex"
      :content-history-length="contentHistory.length" :selected-text="selectedText"
      class="fixed inset-0 z-50 bg-background" @update:chapter-title="chapterTitle = $event"
      @update:chapter-content="handleContentUpdate" @update:selected-text="selectedText = $event"
      @update:show-preview="showPreview = $event" @save="(autoSave) => manualSave()" @undo="handleUndo"
      @redo="handleRedo" @exit-chat-mode="toggleChatMode" />
  </Transition>

  <div class="manual-editor h-screen flex flex-col bg-background dark:bg-background transition-colors duration-300"
    :class="{ 'dark': isEditorDark }">
    <!-- Header -->
    <header
      class="border-b h-14 md:h-16 px-3 md:px-6 flex justify-between items-center bg-background/80 backdrop-blur-md sticky top-0 z-50 transition-all duration-200">
      <div class="flex items-center gap-2 md:gap-6">
        <!-- Back Button -->
        <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
          @click="router.visit(route('projects.show', project.slug))" title="Back to Project">
          <ArrowLeft class="w-4 h-4" />
        </Button>

        <Separator orientation="vertical" class="h-6" />

        <!-- Mobile Menu Buttons (Mobile Only) -->
        <Button @click="showLeftSidebar = true" variant="ghost" size="icon"
          class="h-8 w-8 lg:hidden text-muted-foreground hover:text-foreground" title="Open Chapter Navigation">
          <Menu class="w-4 h-4" />
        </Button>

        <!-- Desktop Left Sidebar Toggle (Desktop Only) -->
        <Button @click="toggleLeftSidebar" variant="ghost" size="icon"
          class="h-8 w-8 transition-all duration-300 hidden lg:flex"
          :class="showLeftSidebar ? 'text-primary' : 'text-zinc-700 dark:text-zinc-300 hover:text-foreground'"
          title="Toggle Chapter Navigation">
          <PanelLeftClose v-if="showLeftSidebar" class="w-4 h-4" />
          <PanelLeftOpen v-else class="w-4 h-4" />
        </Button>

        <Separator orientation="vertical" class="h-6" />

        <!-- Project & Chapter Info -->
        <div class="flex flex-col justify-center min-w-0 flex-1">
          <div class="hidden md:flex items-center gap-2 text-xs font-medium text-muted-foreground mb-1">
            <SafeHtmlText as="span" class="hover:text-foreground transition-colors cursor-pointer truncate"
              :content="project.title" @click="router.visit(route('projects.show', project.slug))" />
            <!-- <ChevronRight class="w-3 h-3 opacity-50 flex-shrink-0" />
            <span class="truncate text-foreground/80">{{ project.topic || 'No Topic' }}</span> -->
          </div>

          <div class="flex items-center gap-2 md:gap-4 -ml-1">
            <!-- Chapter Title -->
            <div class="font-bold text-base md:text-xl text-foreground truncate">
              <span class="truncate">Ch. {{ chapter.chapter_number }}<span class="hidden sm:inline">: {{ chapter.title
                  }}</span></span>
            </div>

            <!-- Quick Nav Buttons (Desktop Only) -->
            <div class="hidden lg:flex items-center gap-2">
              <Button v-if="prevChapter" variant="ghost" size="sm"
                class="h-8 px-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground gap-1.5"
                @click="goToChapter(prevChapter.chapter_number)" :title="`Previous: ${prevChapter.title}`">
                <ChevronLeft class="w-4 h-4" />
                <span class="hidden xl:inline text-xs font-medium">Prev: Chapter {{ prevChapter.chapter_number }}</span>
              </Button>

              <Button v-if="nextChapter" variant="ghost" size="sm"
                class="h-8 px-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground gap-1.5"
                @click="goToChapter(nextChapter.chapter_number)" :title="`Next: ${nextChapter.title}`">
                <span class="hidden xl:inline text-xs font-medium">Next: Chapter {{ nextChapter.chapter_number }}</span>
                <ChevronRight class="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex gap-2 md:gap-3 items-center">
        <!-- Status Indicator -->
        <div
          class="hidden md:flex items-center gap-1 md:gap-2 px-2 md:px-3 py-1.5 rounded-full bg-muted/30 border border-border/40">
          <div class="flex items-center gap-1 md:gap-1.5">
            <div class="w-2 h-2 rounded-full transition-colors duration-300"
              :class="isDirty ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></div>
            <span class="text-xs font-medium text-muted-foreground hidden sm:inline">
              {{ isDirty ? 'Unsaved' : 'Saved' }}
            </span>
          </div>

          <Separator orientation="vertical" class="h-3 hidden sm:block" />

          <div v-if="isAnalyzing" class="flex items-center gap-1 md:gap-1.5">
            <Loader2 class="w-3 h-3 animate-spin text-primary" />
            <span class="text-xs text-muted-foreground hidden md:inline">Analyzing</span>
          </div>
          <div v-else class="text-xs text-muted-foreground hidden md:block">
            {{ currentAnalysis?.word_count ?? chapter.word_count }}<span class="hidden sm:inline"> words</span>
          </div>
        </div>

        <!-- Save Button (Desktop) -->
        <Button variant="outline" size="sm"
          class="hidden md:flex h-9 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
          @click="handleManualSave" :disabled="isSaving" title="Save (Ctrl+S)">
          <Save class="w-4 h-4" />
          <span class="text-xs font-medium">{{ isSaving ? 'Saving...' : 'Save' }}</span>
        </Button>

        <!-- Save Button (Mobile - Icon only) -->
        <Button variant="ghost" size="icon" class="h-8 w-8 md:hidden" @click="handleManualSave" :disabled="isSaving"
          title="Save">
          <Save class="w-4 h-4" />
        </Button>

        <!-- Export Menu -->
        <ExportMenu :project="project" :current-chapter="chapter" :all-chapters="allChapters" size="sm"
          variant="outline" class="h-9 hidden md:flex" />

        <Button variant="ghost" size="sm"
          class="hidden md:flex h-9 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
          @click="goToBulkAnalysis" title="Open bulk analysis">
          <Brain class="w-4 h-4" />
          <span class="text-xs font-medium">Analyze</span>
        </Button>

        <Separator orientation="vertical" class="h-6 mx-1 opacity-50" />

        <!-- Mobile Right Menu Button (Mobile Only) -->
        <Button @click="showRightSidebar = true" variant="ghost" size="icon"
          class="h-8 w-8 lg:hidden text-muted-foreground hover:text-foreground" title="Open Tools">
          <MessageSquare class="w-4 h-4" />
        </Button>

        <!-- View Controls (Desktop Only) -->
        <div class="hidden lg:flex items-center gap-1 bg-muted/30 p-1 rounded-lg border border-border/40">
          <Button variant="ghost" size="icon"
            class="h-7 w-7 rounded-md text-zinc-700 dark:text-zinc-300 hover:text-foreground" @click="toggleEditorTheme"
            :title="isEditorDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
            <Moon v-if="isEditorDark" class="w-4 h-4" />
            <Sun v-else class="w-4 h-4" />
          </Button>

          <Button variant="ghost" size="icon"
            class="h-7 w-7 rounded-md text-zinc-700 dark:text-zinc-300 hover:text-foreground"
            @click="toggleNativeFullscreen" :title="isNativeFullscreen ? 'Exit Full Screen' : 'Enter Full Screen'">
            <Minimize2 v-if="isNativeFullscreen" class="w-4 h-4" />
            <Maximize2 v-else class="w-4 h-4" />
          </Button>

          <Button @click="toggleChatMode" variant="ghost" size="icon"
            class="h-7 w-7 rounded-md transition-all duration-300"
            :class="showChatMode ? 'bg-background shadow-sm text-primary' : 'text-zinc-700 dark:text-zinc-300 hover:text-foreground'"
            title="Toggle AI Chat Mode">
            <MessageSquare class="w-4 h-4" />
          </Button>

          <Button @click="toggleRightSidebar" variant="ghost" size="icon"
            class="h-7 w-7 rounded-md transition-all duration-300"
            :class="showRightSidebar ? 'bg-background shadow-sm text-primary' : 'text-zinc-700 dark:text-zinc-300 hover:text-foreground'"
            title="Toggle Tools Sidebar">
            <PanelRightClose v-if="showRightSidebar" class="w-4 h-4" />
            <PanelRightOpen v-else class="w-4 h-4" />
          </Button>
        </div>
      </div>
    </header>

    <!-- Progress Bar Section -->
    <div class="w-full bg-background border-b px-3 md:px-6 py-2 flex flex-col gap-1">
      <div class="flex justify-between items-center text-xs text-muted-foreground mb-1">
        <span class="font-medium">Writing Progress</span>
        <span>{{ currentAnalysis?.word_count ?? chapter.word_count }} / {{ chapter.target_word_count }} words</span>
      </div>
      <Progress :model-value="progressPercentage" class="h-1.5" />
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left Sidebar (Chapter Navigation) - Desktop Only -->
      <Transition enter-active-class="transition-all duration-500 cubic-bezier(0.4, 0, 0.2, 1)"
        enter-from-class="-ml-[320px] opacity-0" enter-to-class="ml-0 opacity-100"
        leave-active-class="transition-all duration-500 cubic-bezier(0.4, 0, 0.2, 1)"
        leave-from-class="ml-0 opacity-100" leave-to-class="-ml-[320px] opacity-0">
        <aside v-if="showLeftSidebar && !isMobile"
          class="w-[320px] border-r bg-background/80 backdrop-blur-xl border-border/50 shadow-xl z-20 overflow-y-auto custom-scrollbar">
          <div class="p-4">
            <div class="mb-3">
              <h2 class="text-sm font-semibold text-foreground mb-1">Table of Contents</h2>
              <p class="text-xs text-muted-foreground">{{ allChapters.length }} Chapters</p>
            </div>
            <ChapterNavigation :all-chapters="(allChapters as any)" :current-chapter="(chapter as any)"
              :project="(project as any)" :outlines="project.outlines || []" :faculty-chapters="facultyChapters || []"
              :current-word-count="currentAnalysis?.word_count || chapter.word_count"
              :target-word-count="chapter.target_word_count" :writing-quality-score="0"
              :chapter-content-length="content?.length || 0" @go-to-chapter="goToChapter"
              @generate-next-chapter="generateNextChapter" @delete-chapter="deleteChapter" />
          </div>
        </aside>
      </Transition>

      <!-- Writing Area -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Editor -->
        <div class="flex-1 overflow-auto p-2 md:p-6">
          <RichTextEditor :modelValue="content" @update:modelValue="updateContent" :manual-mode="true"
            class="min-h-full" />
        </div>
      </div>

      <!-- Right Sidebar (Tools & Guidance) -->
      <Transition enter-active-class="transition-all duration-500 cubic-bezier(0.4, 0, 0.2, 1)"
        enter-from-class="-mr-[400px] opacity-0" enter-to-class="mr-0 opacity-100"
        leave-active-class="transition-all duration-500 cubic-bezier(0.4, 0, 0.2, 1)"
        leave-from-class="mr-0 opacity-100" leave-to-class="-mr-[400px] opacity-0">
        <aside v-if="showRightSidebar && !isMobile"
          class="w-[400px] border-l bg-background/80 backdrop-blur-xl border-border/50 shadow-2xl z-20 overflow-y-auto custom-scrollbar flex flex-col">
          <div class="p-4 space-y-6">
            <!-- Smart Suggestion Panel (shown when suggestion exists) -->
            <div v-if="currentSuggestion" class="space-y-2">
              <SmartSuggestionPanel :suggestion="currentSuggestion" :analysis="currentAnalysis" @save="saveSuggestion"
                @clear="clearSuggestion" @apply="applySuggestion" />
              <Separator class="bg-border/50" />
            </div>

            <!-- Progressive Guidance Panel -->
            <ProgressiveGuidancePanel :guidance="guidance" :is-loading="isLoadingGuidance" @toggle-step="toggleStep" />

            <Separator class="bg-border/50" />

            <!-- Quick Actions Panel -->
            <QuickActionsPanel :project-slug="project.slug" :chapter-number="chapter.chapter_number"
              :selected-text="selectedText" :is-processing="isAnalyzing || isSaving" @text-improved="handleTextImproved"
              @text-expanded="handleTextExpanded" @citations-suggested="handleCitationsSuggested"
              @text-rephrased="handleTextRephrased" />

            <Separator class="bg-border/50" />

            <!-- Citation Helper -->
            <div class="space-y-2">
              <CitationHelper v-model:show-citation-helper="showCitationHelper" :chapter-content="content"
                @insert-citation="handleInsertCitation" />
            </div>

            <Separator class="bg-border/50" />

            <!-- Defense Preparation Panel -->
            <DefensePreparationPanel v-model:show-defense-prep="showDefensePrep" :questions="defenseQuestions"
              :is-loading="false" :is-generating="isGeneratingDefense" :chapter-context="{
                chapter_number: chapter.chapter_number,
                chapter_title: chapter.title,
                word_count: currentAnalysis?.word_count || chapter.word_count || 0,
              }" @generate-more="() => { }" @refresh="() => { }" />

            <Separator class="bg-border/50" />

            <!-- Chat Assistant Panel Removed -->
          </div>
        </aside>
      </Transition>



      <!-- Mobile Overlays -->
      <MobileNavOverlay :show-left-sidebar="showLeftSidebar" :show-right-sidebar="showRightSidebar"
        :is-mobile="isMobile" :all-chapters="allChapters" :current-chapter="chapter" :project="project"
        :faculty-chapters="facultyChapters" :current-word-count="currentAnalysis?.word_count || chapter.word_count"
        :target-word-count="chapter.target_word_count" :chapter-content-length="content?.length || 0"
        :selected-text="selectedText" :is-analyzing="isAnalyzing" :is-saving="isSaving"
        :current-suggestion="currentSuggestion" :current-analysis="currentAnalysis" :guidance="guidance"
        :is-loading-guidance="isLoadingGuidance" :chapter-content="content" :show-citation-helper="showCitationHelper"
        @update:show-left-sidebar="showLeftSidebar = $event" @update:show-right-sidebar="showRightSidebar = $event"
        @update:show-citation-helper="showCitationHelper = $event" @go-to-chapter="goToChapter"
        @generate-next-chapter="generateNextChapter" @delete-chapter="deleteChapter" @save-suggestion="saveSuggestion"
        @clear-suggestion="clearSuggestion" @apply-suggestion="applySuggestion" @toggle-step="toggleStep"
        @text-improved="handleTextImproved" @text-expanded="handleTextExpanded"
        @citations-suggested="handleCitationsSuggested" @text-rephrased="handleTextRephrased"
        @insert-citation="handleInsertCitation" />
    </div>

    <!-- Footer -->
    <footer class="border-t p-2 md:p-3 flex flex-col gap-3 text-xs md:text-sm bg-muted/30">
      <!-- Stats & Actions Row -->
      <div class="flex flex-col sm:flex-row justify-between gap-3 sm:gap-2 items-start sm:items-center w-full">
        <!-- Left: Stats -->
        <div class="flex gap-2 md:gap-4 flex-wrap items-center">
          <span class="text-muted-foreground">
            <span class="hidden sm:inline">Words: </span><strong class="text-foreground">{{ currentAnalysis?.word_count
              ?? chapter.word_count }}</strong> / {{ chapter.target_word_count }}
          </span>
          <span v-if="currentAnalysis" class="text-muted-foreground hidden sm:inline">
            Citations: <strong class="text-foreground">{{ currentAnalysis.citation_count }}</strong>
          </span>
          <span v-if="currentAnalysis" class="text-muted-foreground hidden md:inline">
            Tables: <strong class="text-foreground">{{ currentAnalysis.table_count }}</strong>
          </span>
          <span v-if="currentAnalysis" class="text-muted-foreground hidden lg:inline">
            Quality: {{ currentAnalysis.quality_metrics.reading_level }}
          </span>
        </div>

        <!-- Right: Action Buttons -->
        <div class="flex gap-2 items-center w-full sm:w-auto">
          <!-- Save Button -->
          <Button variant="outline" size="sm" class="h-8 md:h-9 gap-2 flex-1 sm:flex-initial" @click="handleManualSave"
            :disabled="isSaving" title="Save chapter">
            <Save class="w-3.5 h-3.5 md:w-4 md:h-4" />
            <span class="text-xs font-medium">{{ isSaving ? 'Saving...' : 'Save' }}</span>
          </Button>

          <!-- Mark Complete Button -->
          <Button variant="default" size="sm" class="h-8 md:h-9 gap-2 flex-1 sm:flex-initial" @click="markAsComplete"
            :disabled="isSaving" title="Save and mark chapter as complete">
            <CheckCircle class="w-3.5 h-3.5 md:w-4 md:h-4" />
            <span class="text-xs font-medium hidden sm:inline">Mark Complete</span>
            <span class="text-xs font-medium sm:hidden">Complete</span>
          </Button>
        </div>
      </div>
    </footer>

    <!-- Toast Notifications -->
    <Toaster position="top-center" />
  </div>
</template>

<style scoped>
/* Custom scrollbar for sidebar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: hsl(var(--border));
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: hsl(var(--border) / 0.8);
}

/* Firefox scrollbar */
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: hsl(var(--border)) transparent;
}

/* Chat Glide Animation */
.chat-glide-enter-active,
.chat-glide-leave-active {
  transition: transform 0.5s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.5s ease;
}

.chat-glide-enter-from,
.chat-glide-leave-to {
  transform: translateY(100%);
  opacity: 0;
}

.chat-glide-enter-to,
.chat-glide-leave-from {
  transform: translateY(0);
  opacity: 1;
}
</style>
