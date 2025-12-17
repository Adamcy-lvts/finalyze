<script setup lang="ts">
/**
 * ManualEditor Debug Page - Phase 4a: Add ExportMenu only
 * 
 * Phase 4 worked. Now adding just ExportMenu.
 */
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { useAppearance } from '@/composables/useAppearance'
import { useManualEditor } from '@/composables/useManualEditor'
import { useManualEditorSuggestions } from '@/composables/useManualEditorSuggestions'
import { useTextHistory } from '@/composables/useTextHistory'
import { Button } from '@/components/ui/button'
import { Toaster } from '@/components/ui/sonner'
import { Separator } from '@/components/ui/separator'
import { Progress } from '@/components/ui/progress'
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue'
import SmartSuggestionPanel from '@/components/manual-editor/SmartSuggestionPanel.vue'
import QuickActionsPanel from '@/components/manual-editor/QuickActionsPanel.vue'
import MobileNavOverlay from '@/components/manual-editor/MobileNavOverlay.vue'
import ChapterNavigation from '@/components/chapter-editor/ChapterNavigation.vue'
import CitationHelper from '@/components/chapter-editor/CitationHelper.vue'
// ChatModeLayout REMOVED - causes dark mode issues
// import ChatModeLayout from '@/components/chapter-editor/ChatModeLayout.vue'
// import SafeHtmlText from '@/components/SafeHtmlText.vue'
import ExportMenu from '@/components/chapter-editor/ExportMenu.vue'
import { Menu, Moon, Sun, PanelLeftOpen, PanelLeftClose, PanelRightOpen, PanelRightClose, Save, Maximize2, Minimize2 } from 'lucide-vue-next'
import type { Project, Chapter, UserChapterSuggestion, ChapterContextAnalysis } from '@/types'

const props = defineProps<{
    project: Project
    chapter: Chapter
    allChapters: Chapter[]
    facultyChapters: any[]
    currentSuggestion?: UserChapterSuggestion | null
    contextAnalysis?: ChapterContextAnalysis | null
}>()

const { appearance, updateAppearance, isDark, toggle } = useAppearance()
const { content, isDirty, isSaving, lastSaved, updateContent, manualSave } = useManualEditor(props.chapter, props.project.slug)
const { currentSuggestion, currentAnalysis, isAnalyzing, clearSuggestion } =
    useManualEditorSuggestions(props.chapter, props.project.slug, props.currentSuggestion ?? null, content)
const { undo, redo, canUndo, canRedo } = useTextHistory(props.chapter.content || '')

const page = usePage()

watch(
    () => page.props?.flash,
    (flash: any) => {
        if (!flash) return
        if (flash.success) toast.success(flash.success)
        if (flash.error) toast.error(flash.error)
    },
    { deep: true, immediate: true }
)

const showLeftSidebar = ref(true)
const showRightSidebar = ref(true)
const showMobileNav = ref(false)
const showChatMode = ref(false)
const isMobile = ref(false)
const isFullscreen = ref(false)
const selectedText = ref('')
const selectionRange = ref<{ from: number; to: number } | null>(null)
const showCitationHelper = ref(true)

if (typeof window !== 'undefined') {
    isMobile.value = window.innerWidth < 768
}

// Fullscreen handlers REMOVED - causes dark mode issues

const debugInfo = ref({ htmlHasDark: false, isDarkValue: false })

function updateDebugInfo() {
    if (typeof window !== 'undefined') {
        debugInfo.value = {
            htmlHasDark: document.documentElement.classList.contains('dark'),
            isDarkValue: isDark.value,
        }
    }
}

onMounted(() => {
    updateDebugInfo()
    setInterval(updateDebugInfo, 500)
})

const handleApplySuggestion = (suggestion: string) => {
    updateContent(content.value + '\n\n' + suggestion)
}

const handleContentInsert = (text: string, position?: { from: number; to: number }) => {
    if (position) {
        const before = content.value?.substring(0, position.from) || ''
        const after = content.value?.substring(position.to) || ''
        updateContent(before + text + after)
    } else {
        updateContent(content.value + '\n\n' + text)
    }
}
</script>

<template>

    <Head :title="`Debug Phase 5: ${chapter.title}`" />
    <Toaster position="top-right" />

    <!-- ChatModeLayout REMOVED - causes dark mode issues -->

    <!-- MobileNavOverlay -->
    <MobileNavOverlay v-if="isMobile" :show="showMobileNav" :project="project" :current-chapter="chapter"
        :all-chapters="allChapters" :faculty-chapters="facultyChapters" @close="showMobileNav = false" />

    <div class="h-screen flex flex-col bg-background transition-colors duration-300">
        <header
            class="border-b h-14 px-3 md:px-6 flex justify-between items-center bg-background/80 backdrop-blur-md sticky top-0 z-50">
            <div class="flex items-center gap-2 md:gap-6">
                <Button variant="ghost" size="icon" class="h-8 w-8 rounded-lg"
                    @click="showLeftSidebar = !showLeftSidebar">
                    <PanelLeftOpen v-if="!showLeftSidebar" class="h-4 w-4" />
                    <PanelLeftClose v-else class="h-4 w-4" />
                </Button>

                <div class="flex flex-col">
                    <span class="text-xs text-orange-500 font-medium">Phase 4a - + ExportMenu</span>
                    <h1 class="text-sm font-semibold truncate max-w-[200px] md:max-w-[400px]">{{ chapter.title }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- NEW: ExportMenu -->
                <ExportMenu :project="project" :chapter="chapter" />

                <Button variant="outline" size="sm" class="h-8 gap-2" @click="manualSave" :disabled="isSaving">
                    <Save class="h-4 w-4" />
                    {{ isSaving ? 'Saving...' : 'Save' }}
                </Button>

                <!-- Fullscreen button REMOVED - causes dark mode issues -->

                <Button variant="outline" size="icon" class="h-8 w-8 rounded-lg" @click="toggle">
                    <Sun v-if="isDark" class="h-4 w-4" />
                    <Moon v-else class="h-4 w-4" />
                </Button>

                <Button variant="ghost" size="icon" class="h-8 w-8 rounded-lg"
                    @click="showRightSidebar = !showRightSidebar">
                    <PanelRightOpen v-if="!showRightSidebar" class="h-4 w-4" />
                    <PanelRightClose v-else class="h-4 w-4" />
                </Button>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <aside v-if="showLeftSidebar && !isMobile"
                class="w-[280px] border-r bg-background/80 backdrop-blur-xl overflow-y-auto flex flex-col">
                <div class="p-4">
                    <div class="p-2 rounded-lg border border-border bg-muted/30 mb-4 text-xs">
                        <div class="flex justify-between items-center">
                            <span>Dark:</span>
                            <span :class="debugInfo.htmlHasDark ? 'text-green-500' : 'text-red-500'">
                                {{ debugInfo.htmlHasDark ? 'ON' : 'OFF' }}
                            </span>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <Button @click="updateAppearance('light')"
                                :variant="appearance === 'light' ? 'default' : 'outline'" size="sm">
                                <Sun class="w-3 h-3" />
                            </Button>
                            <Button @click="updateAppearance('dark')"
                                :variant="appearance === 'dark' ? 'default' : 'outline'" size="sm">
                                <Moon class="w-3 h-3" />
                            </Button>
                        </div>
                    </div>

                    <ChapterNavigation :project="project" :current-chapter="chapter" :all-chapters="allChapters"
                        :faculty-chapters="facultyChapters" />
                </div>
            </aside>

            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-auto p-2 md:p-6 bg-background">
                    <RichTextEditor :modelValue="content" @update:modelValue="updateContent"
                        @update:selectedText="selectedText = $event" @update:selectionRange="selectionRange = $event"
                        :manual-mode="true" class="min-h-full" />
                </div>
            </div>

            <aside v-if="showRightSidebar && !isMobile"
                class="w-[400px] border-l bg-background/80 backdrop-blur-xl overflow-y-auto flex flex-col">
                <div class="p-4 space-y-4">
                    <QuickActionsPanel
                        :project-slug="project.slug"
                        :chapter-number="chapter.chapter_number"
                        :selected-text="selectedText"
                        :chapter-content="content"
                        @text-improved="handleApplySuggestion"
                        @text-expanded="(t) => handleContentInsert(t)"
                        @citations-suggested="(t) => { showCitationHelper = true; handleContentInsert(t) }"
                        @text-rephrased="(t) => handleContentInsert(t)"
                    />

                    <CitationHelper v-model:show-citation-helper="showCitationHelper" :chapter-content="content"
                        :chapter-id="chapter.id" @insert-citation="(c) => handleContentInsert(c)" />

                    <!-- NEW: SmartSuggestionPanel -->
                    <SmartSuggestionPanel v-if="currentSuggestion" :suggestion="currentSuggestion"
                        :is-analyzing="isAnalyzing" @apply="handleApplySuggestion" @dismiss="clearSuggestion" />
                </div>
            </aside>
        </div>
    </div>
</template>
