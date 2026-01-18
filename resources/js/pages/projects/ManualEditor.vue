<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue-sonner'
import { MessageSquare, Loader2, PanelRightClose, PanelRightOpen, PanelLeftClose, PanelLeftOpen, ChevronLeft, ChevronRight, ArrowLeft, Menu, Save, Maximize2, Minimize2, CheckCircle, Brain, Moon, Sun, HelpCircle } from 'lucide-vue-next'
import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Progress } from '@/components/ui/progress'
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue'
import SmartSuggestionPanel from '@/components/manual-editor/SmartSuggestionPanel.vue'
import QuickActionsPanel from '@/components/manual-editor/QuickActionsPanel.vue'
import ChapterStarterOverlay from '@/components/manual-editor/ChapterStarterOverlay.vue'
import MobileNavOverlay from '@/components/manual-editor/MobileNavOverlay.vue'
import ManualChatSidebar from '@/components/manual-editor/ManualChatSidebar.vue'
// DefensePreparationPanel DISABLED - causes dark mode issues
// import DefensePreparationPanel from '@/components/chapter-editor/DefensePreparationPanel.vue'
import ChapterNavigation from '@/components/chapter-editor/ChapterNavigation.vue'
import ExportMenu from '@/components/chapter-editor/ExportMenu.vue'
// SafeHtmlText DISABLED - may cause dark mode issues
// import SafeHtmlText from '@/components/SafeHtmlText.vue'
import { Toaster } from '@/components/ui/sonner'
import CitationHelper from '@/components/chapter-editor/CitationHelper.vue'
import WordBalanceDisplay from '@/components/WordBalanceDisplay.vue'
import { useManualEditor } from '@/composables/useManualEditor'
import { useManualEditorSuggestions } from '@/composables/useManualEditorSuggestions'
import { useTextHistory } from '@/composables/useTextHistory'
import PurchaseModal from '@/components/PurchaseModal.vue'
import { recordWordUsage, useWordBalance } from '@/composables/useWordBalance'
import FeedbackPromptModal from '@/components/FeedbackPromptModal.vue'
// ChatModeLayout DISABLED - causes dark mode issues
// import ChatModeLayout from '@/components/chapter-editor/ChatModeLayout.vue'
import { useAppearance } from '@/composables/useAppearance'
import { useManualChat } from '@/composables/useManualChat'
import { useChapterStarter } from '@/composables/useChapterStarter'
import { useAIAutocomplete } from '@/composables/useAIAutocomplete'
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
  initialProgressGuidance?: any | null
  chatHistory: ChatMessage[]
}>()


// Get page instance for flash messages
const page = usePage()

const showFeedbackPrompt = ref(false)
const feedbackRequestId = ref<number | null>(null)
const feedbackRating = ref<number | null>(null)
const feedbackComment = ref('')
const feedbackCommentError = ref('')
const feedbackSubmitting = ref(false)
const feedbackDismissing = ref(false)
const feedbackSource = 'manual_editor'

const isFeedbackBusy = computed(() => feedbackSubmitting.value || feedbackDismissing.value)
const feedbackCanSubmit = computed(() => {
  if (feedbackRating.value === null) return false
  if (feedbackRating.value < 3) {
    return feedbackComment.value.trim().length > 0
  }
  return true
})

const feedbackContext = computed(() => ({
  page: 'manual_editor',
  project_id: props.project?.id,
  chapter_id: props.chapter?.id,
  path: typeof window !== 'undefined' ? window.location.pathname : null,
}))

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const resetFeedbackState = () => {
  showFeedbackPrompt.value = false
  feedbackRequestId.value = null
  feedbackRating.value = null
  feedbackComment.value = ''
  feedbackCommentError.value = ''
}

const fetchFeedbackEligibility = async () => {
  if (!page.props.auth?.user || !props.project?.id) return

  try {
    const response = await fetch(
      route('api.feedback.eligibility', {
        project_id: props.project.id,
        source: feedbackSource,
      }),
    )

    if (!response.ok) return
    const data = await response.json()
    if (!data.eligible) return

    if (data.existing_request_id) {
      feedbackRequestId.value = data.existing_request_id
      showFeedbackPrompt.value = true
      return
    }

    const createResponse = await fetch(route('api.feedback.requests.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        project_id: props.project.id,
        source: feedbackSource,
        context: feedbackContext.value,
      }),
    })

    if (!createResponse.ok) return
    const created = await createResponse.json()
    feedbackRequestId.value = created.id
    showFeedbackPrompt.value = true
  } catch (error) {
    console.error('Failed to check feedback eligibility:', error)
  }
}

const submitFeedback = async () => {
  if (!feedbackRequestId.value || !feedbackCanSubmit.value) {
    feedbackCommentError.value = feedbackRating.value && feedbackRating.value < 3
      ? 'Please add a short comment.'
      : ''
    return
  }

  feedbackSubmitting.value = true
  feedbackCommentError.value = ''

  try {
    const response = await fetch(
      route('api.feedback.requests.submit', { feedbackRequest: feedbackRequestId.value }),
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({
          rating: feedbackRating.value,
          comment: feedbackComment.value.trim() || null,
        }),
      },
    )

    if (!response.ok) {
      const data = await response.json().catch(() => ({}))
      feedbackCommentError.value = data?.errors?.comment?.[0] ?? ''
      return
    }

    resetFeedbackState()
    toast.success('Thanks for the feedback!')
  } catch (error) {
    console.error('Failed to submit feedback:', error)
  } finally {
    feedbackSubmitting.value = false
  }
}

const dismissFeedback = async () => {
  if (!feedbackRequestId.value) {
    resetFeedbackState()
    return
  }

  feedbackDismissing.value = true

  try {
    await fetch(route('api.feedback.requests.dismiss', { feedbackRequest: feedbackRequestId.value }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
    })
  } catch (error) {
    console.error('Failed to dismiss feedback prompt:', error)
  } finally {
    feedbackDismissing.value = false
    resetFeedbackState()
  }
}

const handleFeedbackOpenChange = (open: boolean) => {
  if (open) {
    showFeedbackPrompt.value = true
    return
  }

  if (feedbackSubmitting.value || feedbackDismissing.value) {
    showFeedbackPrompt.value = open
    return
  }

  dismissFeedback()
}

watch([feedbackRating, feedbackComment], () => {
  if (feedbackCommentError.value) {
    feedbackCommentError.value = ''
  }
})

// Watch for flash messages and show toasts
// Watch for flash messages and show toasts
watch(
  () => page.props?.flash,
  (flash: any) => {
    if (!flash) return
    if (flash.success) {
      toast.success(flash.success)
    }
    if (flash.error) {
      toast.error(flash.error)
    }
  },
  { deep: true, immediate: true }
)

const {
  wordBalance,
  balance,
  showPurchaseModal,
  requiredWordsForModal,
  actionDescriptionForModal,
  checkAndPrompt,
  closePurchaseModal,
  refreshBalance,
} = useWordBalance()

const { content, isDirty, isSaving, lastSaved, updateContent, manualSave } = useManualEditor(props.chapter, props.project.slug)

const { currentSuggestion, currentAnalysis, isAnalyzing, saveSuggestion, clearSuggestion, applySuggestion } =
  useManualEditorSuggestions(props.chapter, props.project.slug, props.currentSuggestion, content, {
    onUsageRecorded: () => refreshBalance(),
  })

const { contentHistory, historyIndex, addToHistory, undo, redo, canUndo, canRedo } = useTextHistory(props.chapter.content || '');

const editorRef = ref<InstanceType<typeof RichTextEditor> | null>(null)

const {
  isChatOpen: showChatMode,
  messages: chatMessages,
  isLoading: isChatLoading,
  toggleChat: toggleChatSidebar,
  sendMessage: sendChatMessage,
  setOnUsageRecorded: setChatOnUsageRecorded,
} = useManualChat(props.chapter, props.chatHistory || [])

setChatOnUsageRecorded(() => refreshBalance())

const chapterTitle = ref(props.chapter.title || '')
const showPreview = ref(false)
const isGenerating = ref(false)
const generationProgress = ref('')

const toggleChatMode = () => {
  if (citationOperationMode.value) {
    exitCitationOperationMode()
  }
  showRightSidebar.value = true
  toggleChatSidebar()
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
  maybeRequestAutocomplete()
};

const isValid = computed(() => {
  const title = chapterTitle.value?.trim();
  const c = content.value?.trim();
  return !!(title && title.length > 0 && c && c.length > 50);
});

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
const selectionRange = ref<{ from: number; to: number } | null>(null)
const citationSuggestions = ref<string>('')

// Defense preparation state
const showDefensePrep = ref(false)
const defenseQuestions = ref([])
const isGeneratingDefense = ref(false)

// Citation Helper state
const showCitationHelper = ref(true)

// Load citation operation mode from localStorage
const loadCitationModeFromStorage = () => {
  try {
    const stored = localStorage.getItem('citationOperationMode')
    return stored === 'true'
  } catch {
    return false
  }
}

const citationOperationMode = ref(loadCitationModeFromStorage())

// Enter/exit citation operation mode (split view) with localStorage persistence
const enterCitationOperationMode = () => {
  citationOperationMode.value = true
  showRightSidebar.value = false // Hide normal sidebar
  try {
    localStorage.setItem('citationOperationMode', 'true')
  } catch { }
}

const exitCitationOperationMode = () => {
  citationOperationMode.value = false
  showRightSidebar.value = true // Restore sidebar
  try {
    localStorage.setItem('citationOperationMode', 'false')
  } catch { }
}

// Fullscreen state - DISABLED due to dark mode issues
const isNativeFullscreen = ref(false)

// Fullscreen functionality TEMPORARILY DISABLED - causes dark mode flickering
/*
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
*/

const toggleNativeFullscreen = async () => {
  // DISABLED - causes dark mode issues
  toast.info('Fullscreen temporarily disabled')
}

/*
const handleFullscreenChange = () => {
  const fullscreenElement = document.fullscreenElement || (document as any).webkitFullscreenElement || (document as any).msFullscreenElement
  isNativeFullscreen.value = !!fullscreenElement
}
*/

const goToBulkAnalysis = () => {
  router.visit(route('projects.analysis', { project: props.project.slug }))
}

onMounted(() => {
  // Fullscreen event listeners DISABLED - causes dark mode issues
  // document.addEventListener('fullscreenchange', handleFullscreenChange)
  // document.addEventListener('webkitfullscreenchange', handleFullscreenChange)
  // document.addEventListener('msfullscreenchange', handleFullscreenChange)
})

onUnmounted(() => {
  // Fullscreen event listeners DISABLED
  // document.removeEventListener('fullscreenchange', handleFullscreenChange)
  // document.removeEventListener('webkitfullscreenchange', handleFullscreenChange)
  // document.removeEventListener('msfullscreenchange', handleFullscreenChange)
})

// Global theme (single source of truth for the entire app)
const { isDark: isEditorDark, toggle: toggleEditorTheme } = useAppearance();

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

const ensureStarterBalance = () => checkAndPrompt(200, 'generate chapter starter')

const {
  starterText,
  isGenerating: isGeneratingStarter,
  showStarter,
  generateStarter,
  acceptStarter,
  dismissStarter,
} = useChapterStarter(props.project.slug, props.chapter, content, {
  canGenerate: ensureStarterBalance,
})

const chapterOutline = computed(() => {
  const structure = (props.facultyChapters || []) as any[]
  const match =
    structure.find((c) => c?.chapter_number === props.chapter.chapter_number) ??
    structure.find((c) => c?.number === props.chapter.chapter_number)

  if (!match) return ''
  const lines: string[] = []
  if (match.description) lines.push(String(match.description))
  if (Array.isArray(match.sections)) {
    for (const section of match.sections) {
      if (!section) continue
      const title = (section.title ?? section.section_title ?? '').toString().trim()
      const desc = (section.description ?? section.section_description ?? '').toString().trim()
      if (title && desc) lines.push(`${title}: ${desc}`)
      else if (title) lines.push(title)
    }
  }
  return lines.join('\n')
})

const {
  ghostText: autocompleteGhostText,
  isLoading: isAutocompleteLoading,
  debouncedRequest: debouncedAutocomplete,
  requestCompletion: requestAutocomplete,
  acceptSuggestion,
  dismissSuggestion,
} = useAIAutocomplete(props.project.slug, props.chapter.chapter_number, editorRef as any)

const activeGhostText = computed(() => {
  if (showStarter.value && starterText.value) return starterText.value
  return autocompleteGhostText.value
})

const activeGhostTextFormat = computed(() => {
  if (showStarter.value && starterText.value) return 'html'
  return 'text'
})

function buildAutocompleteContext() {
  const tiptap = editorRef.value?.editor?.()
  if (!tiptap) return null

  const selection = tiptap.state.selection
  if (!selection.empty) return null
  if (showStarter.value) return null

  const doc = tiptap.state.doc
  const from = selection.from
  const to = selection.to

  // Provide more context for coherence (include previous paragraphs/sections).
  const beforeStart = Math.max(0, from - 2500)
  const afterEnd = Math.min(doc.content.size, to + 500)

  const textBefore = doc.textBetween(beforeStart, from, '\n', '\n')
  const textAfter = doc.textBetween(to, afterEnd, '\n', '\n')

  // Determine the current section heading (last heading before cursor).
  let sectionHeading = ''
  let lastHeadingPos = 0
  const scanFrom = Math.max(0, from - 8000)
  doc.nodesBetween(scanFrom, from, (node, pos) => {
    if (node.type?.name === 'heading' && pos < from) {
      sectionHeading = node.textContent || ''
      lastHeadingPos = pos
    }
  })

  // If heading is in another part of the doc (e.g., chapter title far above),
  // only keep it when it’s relatively close to the cursor.
  if (sectionHeading && from - lastHeadingPos > 2000) {
    sectionHeading = ''
  }

  let sectionOutline = ''
  if (sectionHeading) {
    const chapterStruct = (props.facultyChapters || []).find(
      (c: any) =>
        c?.chapter_number === props.chapter.chapter_number || c?.number === props.chapter.chapter_number,
    )

    const sections: any[] = Array.isArray(chapterStruct?.sections) ? chapterStruct.sections : []
    const normalizedHeading = sectionHeading.trim().toLowerCase()
    const matched = sections.find((s) => {
      const title = (s?.title ?? s?.section_title ?? '').toString().trim().toLowerCase()
      return title && title === normalizedHeading
    })

    if (matched) {
      const title = (matched.title ?? matched.section_title ?? '').toString().trim()
      const desc = (matched.description ?? matched.section_description ?? '').toString().trim()
      sectionOutline = desc ? `${title}: ${desc}` : title
    }
  }

  return {
    textBefore,
    textAfter,
    chapterNumber: props.chapter.chapter_number,
    chapterTitle: chapterTitle.value || props.chapter.title || '',
    chapterOutline: chapterOutline.value || '',
    sectionHeading: sectionHeading.trim(),
    sectionOutline: sectionOutline.trim(),
    projectTopic: (props.project as any)?.topic || '',
  }
}

function maybeRequestAutocomplete() {
  if (showStarter.value) return
  if (isAutocompleteLoading.value) return
  if (autocompleteGhostText.value) return

  const ctx = buildAutocompleteContext()
  if (!ctx) return
  void (debouncedAutocomplete as any)(ctx).catch(() => { })
}

const regenerateStarter = async () => {
  try {
    await generateStarter()
  } catch (error) {
    console.error('Failed to generate starter:', error)
    toast.error('Failed to generate starter')
  }
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

  fetchFeedbackEligibility()

  onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    window.removeEventListener('keydown', handleKeyboardShortcut)
  })
})

// Handle quick action results (placeholder - would integrate with editor)
const handleTextImproved = (text: string) => {
  handleContentUpdate(text)
  toast.success('Applied improved text')
}

const handleTextExpanded = (text: string) => {
  const ok = editorRef.value?.replaceSelection(text, selectionRange.value || undefined) ?? false
  if (ok) {
    toast.success('Expanded selection')
    return
  }
  handleContentUpdate(`${content.value}\n\n${text}`)
  toast.success('Added expanded text')
}

const handleCitationsSuggested = (suggestions: string) => {
  citationSuggestions.value = suggestions
  showCitationHelper.value = true
  try {
    navigator.clipboard?.writeText(suggestions)
  } catch {
    // ignore
  }
  toast.success('Citation suggestions ready')
}

const handleTextRephrased = (alternatives: string) => {
  const ok = editorRef.value?.replaceSelection(alternatives, selectionRange.value || undefined) ?? false
  if (ok) {
    toast.success('Applied rephrased text')
    return
  }
  handleContentUpdate(`${content.value}\n\n${alternatives}`)
  toast.success('Added rephrased text')
}

const ensureQuickActionBalance = (requiredWords: number, action: string) => checkAndPrompt(requiredWords, action)

const recordManualUsage = async (wordsUsed: number, description: string) => {
  const normalized = Math.max(0, Math.round(wordsUsed || 0))
  if (!normalized) return
  await recordWordUsage(normalized, description, 'chapter', props.chapter.id)
  await refreshBalance()
}

const stripTags = (value: string) => value.replace(/<[^>]*>/g, ' ')
const countWords = (value: string) => stripTags(value).split(/\s+/).filter(Boolean).length

const handleGhostAccepted = async (text: string) => {
  const wordsUsed = countWords(text)
  if (showStarter.value) {
    acceptStarter()
    await recordManualUsage(wordsUsed, 'chapter starter accepted')
    return
  }

  acceptSuggestion()
  await recordManualUsage(wordsUsed, 'autocomplete accepted')
}

const handleGhostDismissed = () => {
  if (showStarter.value) {
    dismissStarter()
    return
  }
  dismissSuggestion()
}

const handleGhostManualTrigger = async () => {
  if (showStarter.value) return
  const ctx = buildAutocompleteContext()
  if (!ctx) return
  try {
    await requestAutocomplete(ctx as any)
  } catch (error) {
    console.error('Failed to autocomplete:', error)
    toast.error('Autocomplete failed')
  }
}

const copyCitationSuggestions = async () => {
  if (!citationSuggestions.value) return
  try {
    await navigator.clipboard.writeText(citationSuggestions.value)
    toast.success('Copied citation suggestions')
  } catch {
    toast.error('Could not copy citation suggestions')
  }
}

const prevChapter = computed(() => props.allChapters.find(c => c.chapter_number === props.chapter.chapter_number - 1))
const nextChapter = computed(() => props.allChapters.find(c => c.chapter_number === props.chapter.chapter_number + 1))

const handleInsertCitation = (citation: string, claimText?: string) => {
  // If we have claim text, try to find it and insert citation at the end of the claim
  if (claimText && content.value) {
    // Clean up the claim text for searching
    const cleanClaimText = claimText.trim()

    // For HTML content, we need to find the text within the HTML structure
    // First, create a temporary DOM element to parse the content
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = content.value
    const plainText = tempDiv.textContent || tempDiv.innerText || ''

    // Find the claim text in plain text
    const claimStart = plainText.indexOf(cleanClaimText)

    if (claimStart !== -1) {
      // Find the end of the sentence containing the claim
      const claimEnd = claimStart + cleanClaimText.length
      const afterClaim = plainText.substring(claimEnd)

      // Find the next sentence ending
      const sentenceEndMatch = afterClaim.match(/^[^.!?]*[.!?]/)
      let insertPosition: number

      if (sentenceEndMatch) {
        insertPosition = claimEnd + sentenceEndMatch[0].length
      } else {
        insertPosition = claimEnd
      }

      // Now we need to find this position in the HTML content
      // Walk through the HTML to find where to insert
      let htmlPos = 0
      let textPos = 0
      const htmlContent = content.value

      while (htmlPos < htmlContent.length && textPos < insertPosition) {
        if (htmlContent[htmlPos] === '<') {
          // Skip HTML tag
          while (htmlPos < htmlContent.length && htmlContent[htmlPos] !== '>') {
            htmlPos++
          }
          htmlPos++ // Skip the '>'
        } else if (htmlContent[htmlPos] === '&') {
          // Handle HTML entities (like &nbsp;)
          const entityEnd = htmlContent.indexOf(';', htmlPos)
          if (entityEnd !== -1 && entityEnd - htmlPos < 10) {
            htmlPos = entityEnd + 1
            textPos++ // Entity counts as one character
          } else {
            htmlPos++
            textPos++
          }
        } else {
          htmlPos++
          textPos++
        }
      }

      // Insert the citation at the found HTML position
      if (htmlPos > 0) {
        const before = htmlContent.substring(0, htmlPos)
        const after = htmlContent.substring(htmlPos)
        const newContent = `${before} ${citation}${after}`

        handleContentUpdate(newContent)
        toast.success('Citation inserted at claim location')
        return
      }
    }
  }

  // Fallback: Try cursor position
  const cursor = editorRef.value?.getCursorPosition?.()
  if (typeof cursor === 'number') {
    const ok = editorRef.value?.replaceTextAt?.({ from: cursor, to: cursor }, ` ${citation} `) ?? false
    if (ok) {
      toast.success('Citation inserted at cursor')
      return
    }
  }

  // Final fallback: Append to content
  handleContentUpdate(`${content.value} ${citation}`)
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

// Tutorial Logic
const startTour = () => {
  const steps = [
    {
      popover: {
        title: 'Welcome to the AI Assisted Editor',
        description: 'Experience a powerful writing environment enhanced by AI. Use the Smart Chapter Starter to kickstart your writing, leverage auto-completion for faster drafting, and access a suite of AI tools to refine your content.',
        popoverClass: 'driver-popover-welcome'
      }
    },
    {
      element: '#back-to-project-btn',
      popover: {
        title: 'Back to Project',
        description: 'Return to your project dashboard.'
      }
    },
    {
      element: '#left-sidebar-panel',
      popover: {
        title: 'Chapter Navigation',
        description: 'Use the Table of Contents to switch between chapters.'
      },
      onHighlightStarted: () => {
        showLeftSidebar.value = true;
      },
      showOnMobile: false
    },
    {
      element: '#save-status',
      popover: {
        title: 'Status & Word Count',
        description: 'Check your save status and current word count at a glance.'
      }
    },
    {
      element: '#manual-editor-toolbar',
      popover: {
        title: 'Formatting Tools',
        description: 'Use these tools to format your text, add links, headings, and lists.'
      }
    },
    {
      element: '#view-controls',
      popover: {
        title: 'View Options',
        description: 'Customize your writing experience with dark mode, full screen, AI chat, and tool sidebars.'
      }
    },
    {
      element: '#toggle-chat-mode',
      popover: {
        title: 'AI Chat Assistant',
        description: 'Open the AI assistant to help you write, brainstorm, or refine your content.'
      }
    },
    {
      element: '#toggle-right-sidebar',
      popover: {
        title: 'Writing Tools Sidebar',
        description: 'Toggle this sidebar to access efficient writing tools.'
      },
      showOnMobile: false
    },
    {
      element: '#right-sidebar-panel',
      popover: {
        title: 'Writing Tools',
        description: 'Access quick tools like Smart Suggestions, Quick Actions (Expand, Rephrase), and more.'
      },
      onHighlightStarted: () => {
        showRightSidebar.value = true;
      },
      showOnMobile: false
    },
    {
      element: '#credit-display',
      popover: {
        title: 'Credits',
        description: 'Keep track of your AI credits. Remember, using AI tools consumes credits.'
      }
    },
    {
      element: '#top-up-btn',
      popover: {
        title: 'Top Up',
        description: 'Running low? Click here to purchase more credits.'
      }
    },
    {
      element: '#export-menu-container',
      popover: {
        title: 'Export',
        description: 'Export your chapter or entire project in various formats.'
      }
    },
    {
      element: '#analyze-button',
      popover: {
        title: 'Bulk Analysis',
        description: 'Analyze multiple chapters at once for comprehensive insights.'
      }
    },
    {
      element: '#progress-bar-section',
      popover: {
        title: 'Writing Progress',
        description: 'Track your progress towards the chapter word count goal.'
      }
    },
    {
      element: '#editor-footer',
      popover: {
        title: 'Footer Controls',
        description: 'View detailed stats like citations and quality metrics, and clear actions.'
      }
    },
    {
      element: '#mark-complete-btn',
      popover: {
        title: 'Mark as Complete',
        description: 'Finished writing? Mark the chapter as complete to proceed.'
      }
    },
    {
      element: '#save-button',
      popover: {
        title: 'Save Work',
        description: 'Manually save your chapter. Auto-save is also active!'
      }
    },
    {
      element: '#reset-tour-button',
      popover: {
        title: 'Need Help?',
        description: 'Click here anytime to restart this tour.'
      }
    }
  ];

  const driverObj = driver({
    showProgress: true,
    animate: true,
    steps: isMobile.value
      ? steps.filter(s => s.showOnMobile !== false)
      : steps
  });

  driverObj.drive();

  const user = page.props.auth.user;
  if (user?.email) {
    localStorage.setItem(`manual_editor_tour_seen_${user.email}`, 'true');
  }
};

onMounted(() => {
  const user = page.props.auth.user;
  if (user?.email) {
    const hasSeenTour = localStorage.getItem(`manual_editor_tour_seen_${user.email}`);
    if (!hasSeenTour) {
      setTimeout(() => {
        startTour();
      }, 1000);
    }
  }
});
</script>

<template>

  <Head :title="`${chapter.title} - Manual Editor`" />

  <FeedbackPromptModal
    :open="showFeedbackPrompt"
    :rating="feedbackRating"
    :comment="feedbackComment"
    :is-submitting="isFeedbackBusy"
    :comment-error="feedbackCommentError"
    :can-submit="feedbackCanSubmit"
    @update:open="handleFeedbackOpenChange"
    @update:rating="feedbackRating = $event"
    @update:comment="feedbackComment = $event"
    @submit="submitFeedback"
    @dismiss="dismissFeedback"
  />

  <!-- ChatModeLayout TEMPORARILY DISABLED - causes dark mode issues
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
  -->

  <div class="manual-editor h-screen flex flex-col bg-background dark:bg-background transition-colors duration-300">
    <!-- Mobile Header (Original Preserved) -->
    <header
      class="md:hidden border-b h-14 px-3 flex justify-between items-center bg-background/80 backdrop-blur-md sticky top-0 z-50 transition-all duration-200">
      <div class="flex items-center gap-2">
        <!-- Back Button -->
        <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
          @click="router.visit(route('projects.show', project.slug))" title="Back to Project">
          <ArrowLeft class="w-4 h-4" />
        </Button>

        <Separator orientation="vertical" class="h-6" />

        <!-- Mobile Menu Buttons -->
        <Button @click="showLeftSidebar = true" variant="ghost" size="icon"
          class="h-8 w-8 lg:hidden text-muted-foreground hover:text-foreground" title="Open Chapter Navigation">
          <Menu class="w-4 h-4" />
        </Button>
      </div>

      <!-- Project & Chapter Info (Center - Mobile Only) -->
      <div class="flex flex-col justify-center min-w-0 flex-1 mx-2">
        <div class="items-center gap-2 text-xs font-medium text-muted-foreground mb-1 hidden sm:flex">
          <span class="hover:text-foreground transition-colors cursor-pointer truncate"
            @click="router.visit(route('projects.show', project.slug))">{{ project.title }}</span>
        </div>

        <div class="flex items-center gap-2 -ml-1">
          <div class="font-bold text-base text-foreground truncate">
            <span class="truncate">Ch. {{ chapter.chapter_number }}</span>
          </div>
        </div>
      </div>

      <div class="flex gap-2 items-center">
        <!-- Save Button (Mobile - Icon only) -->
        <Button variant="ghost" size="icon" class="h-8 w-8" @click="handleManualSave" :disabled="isSaving" title="Save">
          <Save class="w-4 h-4" />
        </Button>

        <Separator orientation="vertical" class="h-6 mx-1 opacity-50" />

        <!-- Mobile Right Menu Button (Mobile Only) -->
        <Button @click="showRightSidebar = true" variant="ghost" size="icon"
          class="h-8 w-8 text-muted-foreground hover:text-foreground" title="Open Tools">
          <MessageSquare class="w-4 h-4" />
        </Button>
      </div>
    </header>

    <!-- Desktop Double-Decker Header (New) -->
    <div
      class="hidden md:flex flex-col w-full z-50 sticky top-0 bg-background/80 backdrop-blur-md transition-all duration-200 border-b">
      <!-- Top Row: Navigation & Context -->
      <div id="editor-header"
        class="h-10 border-b border-border/40 flex items-center justify-center px-4 bg-background/60 relative">
        <!-- Title & Badge -->
        <div class="flex items-center gap-2">
          <h1 id="project-title-link"
            class="text-sm font-bold text-foreground cursor-pointer hover:text-primary transition-colors"
            @click="router.visit(route('projects.show', project.slug))">
            {{ project.title }}
          </h1>
          <Badge id="chapter-badge" variant="secondary" class="h-5 px-2 text-[10px] rounded-full">Ch {{
            chapter.chapter_number }}</Badge>
        </div>

        <!-- Quick Nav (Right aligned in center block or absolute right?) 
                  Let's keep it next to title for context or move to right. 
                  User didn't specify, but 'clean top' implies minimal. 
                  I'll place it slightly separated or keep it if it fits cleanliness.
                  The user asked to remove "chapter title". Quick Nav is navigation. 
                  I'll keep it but make it subtle. -->
        <div id="chapter-nav-buttons" class="flex items-center gap-1 absolute right-4">
          <Button v-if="prevChapter" variant="ghost" size="sm"
            class="h-6 px-2 text-zinc-500 hover:text-foreground gap-1 rounded-full text-[10px]"
            @click="goToChapter(prevChapter.chapter_number)" :title="`Previous: ${prevChapter.title}`">
            <ChevronLeft class="w-3 h-3" />
            Prev
          </Button>

          <Button v-if="nextChapter" variant="ghost" size="sm"
            class="h-6 px-2 text-zinc-500 hover:text-foreground gap-1 rounded-full text-[10px]"
            @click="goToChapter(nextChapter.chapter_number)" :title="`Next: ${nextChapter.title}`">
            Next
            <ChevronRight class="w-3 h-3" />
          </Button>
        </div>
      </div>

      <!-- Bottom Row: Tools & Actions -->
      <div class="h-12 flex items-center justify-between px-4 bg-background/40">
        <!-- Left: Nav & Status -->
        <div id="left-controls" class="flex items-center gap-3">
          <!-- Nav Controls (Moved here) -->
          <div class="flex items-center gap-1">
            <Button id="back-to-project-btn" variant="ghost" size="icon"
              class="h-8 w-8 text-zinc-700 dark:text-zinc-300 hover:text-foreground rounded-full"
              @click="router.visit(route('projects.show', project.slug))" title="Back to Project">
              <ArrowLeft class="w-4 h-4" />
            </Button>

            <div class="h-4 w-px bg-border/50 mx-1"></div>

            <Button id="toggle-left-sidebar" @click="toggleLeftSidebar" variant="ghost" size="icon"
              class="h-8 w-8 transition-all duration-300 rounded-full"
              :class="showLeftSidebar ? 'text-primary' : 'text-zinc-700 dark:text-zinc-300 hover:text-foreground'"
              title="Toggle Chapter Navigation">
              <PanelLeftClose v-if="showLeftSidebar" class="w-4 h-4" />
              <PanelLeftOpen v-else class="w-4 h-4" />
            </Button>
          </div>

          <div class="h-4 w-px bg-border/50"></div>

          <!-- Status -->
          <div id="save-status"
            class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-muted/30 border border-border/40">
            <div class="flex items-center gap-1.5">
              <div class="w-2 h-2 rounded-full transition-colors duration-300"
                :class="isDirty ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"></div>
              <span class="text-xs font-medium text-muted-foreground">
                {{ isDirty ? 'Unsaved' : 'Saved' }}
              </span>
            </div>
            <Separator orientation="vertical" class="h-3" />
            <div v-if="isAnalyzing" class="flex items-center gap-1.5">
              <Loader2 class="w-3 h-3 animate-spin text-primary" />
              <span class="text-xs text-muted-foreground">Analyzing</span>
            </div>
            <div v-else class="text-xs text-muted-foreground">
              {{ currentAnalysis?.word_count ?? chapter.word_count }} words
            </div>
          </div>
        </div>

        <!-- Center: Editor Toolbar -->
        <div class="flex-1 px-4 min-w-0">
          <div id="manual-editor-toolbar" class="mx-auto max-w-[950px]"></div>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-3">
          <!-- View Controls -->
          <div id="view-controls" class="flex items-center gap-1 bg-muted/30 p-1 rounded-full border border-border/40">
            <Button variant="ghost" size="icon"
              class="h-7 w-7 rounded-full text-zinc-700 dark:text-zinc-300 hover:text-foreground"
              @click="toggleEditorTheme" :title="isEditorDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
              <Moon v-if="isEditorDark" class="w-4 h-4" />
              <Sun v-else class="w-4 h-4" />
            </Button>

            <Button variant="ghost" size="icon"
              class="h-7 w-7 rounded-full text-zinc-700 dark:text-zinc-300 hover:text-foreground"
              @click="toggleNativeFullscreen" :title="isNativeFullscreen ? 'Exit Full Screen' : 'Enter Full Screen'">
              <Minimize2 v-if="isNativeFullscreen" class="w-4 h-4" />
              <Maximize2 v-else class="w-4 h-4" />
            </Button>

            <Button id="toggle-chat-mode" @click="toggleChatMode" variant="ghost" size="icon"
              class="h-7 w-7 rounded-full transition-all duration-300"
              :class="showChatMode ? 'bg-background shadow-sm text-primary' : 'text-zinc-700 dark:text-zinc-300 hover:text-foreground'"
              title="Toggle AI Chat Mode">
              <MessageSquare class="w-4 h-4" />
            </Button>

            <Button id="toggle-right-sidebar" @click="toggleRightSidebar" variant="ghost" size="icon"
              class="h-7 w-7 rounded-full transition-all duration-300"
              :class="showRightSidebar ? 'bg-background shadow-sm text-primary' : 'text-zinc-700 dark:text-zinc-300 hover:text-foreground'"
              title="Toggle Tools Sidebar">
              <PanelRightClose v-if="showRightSidebar" class="w-4 h-4" />
              <PanelRightOpen v-else class="w-4 h-4" />
            </Button>
          </div>

          <div class="w-px h-4 bg-border/50"></div>

          <div id="credit-display">
            <WordBalanceDisplay v-if="wordBalance" :balance="wordBalance" compact />
          </div>

          <div id="export-menu-container">
            <ExportMenu :project="project" :current-chapter="chapter" :all-chapters="allChapters" size="sm"
              variant="outline" class="h-9" button-class="rounded-full bg-background/50" />
          </div>

          <Button id="analyze-button" variant="ghost" size="sm"
            class="h-9 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground rounded-full bg-background/50 border border-transparent hover:border-border/50"
            @click="goToBulkAnalysis" title="Open bulk analysis">
            <Brain class="w-4 h-4" />
            <span class="text-xs font-medium">Analyze</span>
          </Button>

          <Button id="save-button" variant="outline" size="sm"
            class="h-9 gap-2 text-zinc-700 dark:text-zinc-300 hover:text-foreground rounded-full bg-background/50"
            @click="handleManualSave" :disabled="isSaving" title="Save (Ctrl+S)">
            <Save class="w-4 h-4" />
            <span class="text-xs font-medium">{{ isSaving ? 'Saving...' : 'Save' }}</span>
          </Button>

          <!-- Reset Tour Button (Hidden but accessible via ID) -->
          <Button id="reset-tour-button" variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground"
            @click="startTour" title="Restart Tour">
            <HelpCircle class="w-4 h-4" />
          </Button>

        </div>
      </div>
    </div>

    <!-- Progress Bar Section -->
    <div id="progress-bar-section" class="w-full bg-background border-b px-3 md:px-6 py-2 flex flex-col gap-1">
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
        <aside id="left-sidebar-panel" v-if="showLeftSidebar && !isMobile"
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
      <div :class="[
        'flex-1 flex overflow-hidden',
        citationOperationMode ? 'flex-row' : 'flex-col'
      ]">
        <!-- Editor (narrower when in operation mode) -->
        <div :class="[
          'flex flex-col overflow-hidden',
          citationOperationMode ? 'w-1/2 border-r border-border/50' : 'flex-1'
        ]">
          <div class="flex-1 overflow-auto p-2 md:p-6 bg-background">
            <RichTextEditor ref="editorRef" :modelValue="content" @update:modelValue="handleContentUpdate"
              @update:selectedText="selectedText = $event" @update:selectionRange="selectionRange = $event"
              :ghost-text="activeGhostText" :ghost-text-format="activeGhostTextFormat"
              @ghost-accepted="handleGhostAccepted" @ghost-dismissed="handleGhostDismissed"
              @ghost-manual-trigger="handleGhostManualTrigger" :manual-mode="true"
              :toolbar-teleport-target="!isMobile ? '#manual-editor-toolbar' : ''" class="min-h-full" />
          </div>
        </div>

        <!-- Citation Operation Panel (shown in split view) -->
        <div v-if="citationOperationMode" class="w-1/2 flex flex-col bg-background overflow-hidden">
          <div class="flex-1 overflow-y-auto custom-scrollbar">
            <CitationHelper v-model:show-citation-helper="showCitationHelper" :chapter-content="content"
              :chapter-id="chapter.id" :citation-operation-mode="true" @insert-citation="handleInsertCitation"
              @exit-operation-mode="exitCitationOperationMode" />
          </div>
        </div>
      </div>

      <!-- Right Sidebar (Tools & Guidance) -->
      <Transition enter-active-class="transition-all duration-500 cubic-bezier(0.4, 0, 0.2, 1)"
        enter-from-class="-mr-[400px] opacity-0" enter-to-class="mr-0 opacity-100"
        leave-active-class="transition-all duration-500 cubic-bezier(0.4, 0, 0.2, 1)"
        leave-from-class="mr-0 opacity-100" leave-to-class="-mr-[400px] opacity-0">
        <aside id="right-sidebar-panel" v-if="showRightSidebar && !isMobile"
          class="w-[400px] border-l bg-background/80 backdrop-blur-xl border-border/50 shadow-2xl z-20 overflow-y-auto custom-scrollbar flex flex-col">
          <div v-if="showChatMode" class="flex-1 overflow-hidden">
            <div class="h-full border-b border-border/50">
              <ManualChatSidebar :messages="chatMessages" :is-loading="isChatLoading"
                :analysis="(currentAnalysis as any)" class="h-full" @send="sendChatMessage" @close="toggleChatMode" />
            </div>
          </div>

          <div v-else class="p-4 space-y-6">
            <!-- Smart Suggestion Panel (shown when suggestion exists) -->
            <div v-if="currentSuggestion" class="space-y-2">
              <SmartSuggestionPanel :suggestion="currentSuggestion" :analysis="currentAnalysis" @save="saveSuggestion"
                @clear="clearSuggestion" @apply="applySuggestion" />
              <Separator class="bg-border/50" />
            </div>

            <!-- Quick Actions Panel -->
            <QuickActionsPanel :project-slug="project.slug" :chapter-number="chapter.chapter_number"
              :selected-text="selectedText" :chapter-content="content" :is-processing="isAnalyzing || isSaving"
              :ensure-balance="ensureQuickActionBalance" :on-usage="recordManualUsage"
              @text-improved="handleTextImproved" @text-expanded="handleTextExpanded"
              @citations-suggested="handleCitationsSuggested" @text-rephrased="handleTextRephrased"
              @open-citation-helper="showCitationHelper = true" />

            <Separator class="bg-border/50" />

            <div v-if="citationSuggestions" class="space-y-2">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-foreground">Citation Suggestions</h3>
                <Button size="sm" variant="outline" @click="copyCitationSuggestions">
                  Copy
                </Button>
              </div>
              <pre
                class="text-xs whitespace-pre-wrap rounded-lg border border-border/50 bg-muted/30 p-3">{{ citationSuggestions }}</pre>
              <Separator class="bg-border/50" />
            </div>

            <!-- Citation Helper -->
            <div class="space-y-2">
              <CitationHelper v-model:show-citation-helper="showCitationHelper" :chapter-content="content"
                :chapter-id="chapter.id" @insert-citation="handleInsertCitation"
                @enter-operation-mode="enterCitationOperationMode" />
            </div>

            <Separator class="bg-border/50" />

            <!-- Defense Preparation Panel - TEMPORARILY DISABLED (causes dark mode issues)
            <DefensePreparationPanel v-model:show-defense-prep="showDefensePrep" :questions="defenseQuestions"
              :is-loading="false" :is-generating="isGeneratingDefense" :chapter-context="{
                chapter_number: chapter.chapter_number,
                chapter_title: chapter.title,
                word_count: currentAnalysis?.word_count || chapter.word_count || 0,
              }" @generate-more="() => { }" @refresh="() => { }" />
            -->

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
        :current-suggestion="currentSuggestion" :current-analysis="currentAnalysis" :chapter-content="content"
        :show-citation-helper="showCitationHelper" :ensure-balance="ensureQuickActionBalance"
        :on-usage="recordManualUsage" @update:show-left-sidebar="showLeftSidebar = $event"
        @update:show-right-sidebar="showRightSidebar = $event"
        @update:show-citation-helper="showCitationHelper = $event" @go-to-chapter="goToChapter"
        @generate-next-chapter="generateNextChapter" @delete-chapter="deleteChapter" @save-suggestion="saveSuggestion"
        @clear-suggestion="clearSuggestion" @apply-suggestion="applySuggestion" @text-improved="handleTextImproved"
        @text-expanded="handleTextExpanded" @citations-suggested="handleCitationsSuggested"
        @text-rephrased="handleTextRephrased" @insert-citation="handleInsertCitation" />
    </div>

    <!-- Footer -->
    <footer id="editor-footer" class="border-t p-2 md:p-3 flex flex-col gap-3 text-xs md:text-sm bg-muted/30">
      <!-- Stats & Actions Row -->
      <div class="flex flex-col sm:flex-row justify-between gap-3 sm:gap-2 items-start sm:items-center w-full">
        <!-- Left: Stats -->
        <div id="footer-stats" class="flex gap-2 md:gap-4 flex-wrap items-center">
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
          <Button id="mark-complete-btn" variant="default" size="sm" class="h-8 md:h-9 gap-2 flex-1 sm:flex-initial"
            @click="markAsComplete" :disabled="isSaving" title="Save and mark chapter as complete">
            <CheckCircle class="w-3.5 h-3.5 md:w-4 md:h-4" />
            <span class="text-xs font-medium hidden sm:inline">Mark Complete</span>
            <span class="text-xs font-medium sm:hidden">Complete</span>
          </Button>
        </div>
      </div>
    </footer>

    <!-- Toast Notifications -->
    <PurchaseModal :open="showPurchaseModal" :current-balance="balance" :required-words="requiredWordsForModal"
      :action="actionDescriptionForModal" @update:open="(v) => showPurchaseModal = v" @close="closePurchaseModal" />
    <Toaster position="top-center" />
  </div>

  <ChapterStarterOverlay :show="showStarter && !!starterText" :is-generating="isGeneratingStarter"
    @regenerate="regenerateStarter" @dismiss="dismissStarter" />
</template>


<style>
.driver-popover-welcome {
  max-width: min(500px, calc(100vw - 32px)) !important;
  width: min(500px, calc(100vw - 32px)) !important;
}

.driver-popover-welcome .driver-popover-title {
  font-size: 1.25rem !important;
}

.driver-popover-welcome .driver-popover-description {
  font-size: 1rem !important;
  line-height: 1.6 !important;
}

/* Mobile responsive adjustments for driver.js tour */
@media (max-width: 640px) {
  .driver-popover-welcome {
    max-width: calc(100vw - 24px) !important;
    width: calc(100vw - 24px) !important;
  }

  .driver-popover-welcome .driver-popover-title {
    font-size: 1.125rem !important;
  }

  .driver-popover-welcome .driver-popover-description {
    font-size: 0.875rem !important;
  }

  /* Ensure all driver.js popovers are responsive on mobile */
  .driver-popover {
    max-width: calc(100vw - 24px) !important;
  }

  .driver-popover-title {
    font-size: 1rem !important;
  }

  .driver-popover-description {
    font-size: 0.875rem !important;
  }
}
</style>

<style scoped>
/* Custom scrollbar for sidebar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: color-mix(in oklab, var(--border) 80%, transparent);
}

/* Firefox scrollbar */
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: var(--border) transparent;
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
