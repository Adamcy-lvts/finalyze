<script setup lang="ts">
import { computed } from 'vue'
import { Download, History, Maximize2, Presentation, RotateCcw, Sparkles } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import DeckViewer from '@/components/defense/DeckViewer.vue'
import WysiwygSlideEditor from '@/components/defense/editor/WysiwygSlideEditor.vue'
import type { WysiwygSlide } from '@/types/wysiwyg'

interface DefenseDeckSlide {
    title: string
    bullets: string[]
    layout?: string
    visuals?: string
    speaker_notes?: string
    image_url?: string
    image_fit?: 'cover' | 'contain'
    image_scale?: number
    image_position_x?: number
    image_position_y?: number
    charts?: unknown[]
    tables?: unknown[]
}

interface SessionHistoryItem {
    id: number
    started_at?: string | null
    created_at?: string | null
    status: string
    questions_asked: number
}

interface ProjectRef {
    id: number
    title: string
    [key: string]: unknown
}

const props = defineProps<{
    project: ProjectRef
    deckSlides: DefenseDeckSlide[]
    deckStatus: string
    deckError: string | null
    deckDownloadUrl: string | null
    deckId: number | null
    activeDeckSlideIndex: number
    isDeckGenerating: boolean
    isDeckSaving: boolean
    isGuideExpanded: boolean
    isProduction: boolean
    wysiwygSlides: WysiwygSlide[]
    sessionHistory: SessionHistoryItem[]
    isHistoryLoading: boolean
}>()

const emit = defineEmits<{
    (e: 'generateDefenseDeck'): void
    (e: 'downloadPptx'): void
    (e: 'update:activeDeckSlideIndex', value: number): void
    (e: 'update:isGuideExpanded', value: boolean): void
    (e: 'update:deckSlides', value: DefenseDeckSlide[]): void
    (e: 'update:wysiwygSlides', value: WysiwygSlide[]): void
    (e: 'openSession', id: number): void
}>()

const activeDeckIndex = computed({
    get: () => props.activeDeckSlideIndex,
    set: value => emit('update:activeDeckSlideIndex', value),
})

const guideExpanded = computed({
    get: () => props.isGuideExpanded,
    set: value => emit('update:isGuideExpanded', value),
})

const formatSessionDate = (dateValue: string | null | undefined) => {
    if (!dateValue) return ''
    return new Date(dateValue).toLocaleDateString()
}
</script>

<template>
    <div class="space-y-8">
        <Card class="border-border/50 shadow-2xl shadow-indigo-500/10 rounded-3xl overflow-hidden bg-zinc-900/20">
            <CardHeader
                class="relative overflow-hidden bg-gradient-to-br from-indigo-950 via-zinc-900 to-black text-white pb-6 border-b border-white/5">
                <div class="absolute -right-6 -top-6 h-32 w-32 bg-indigo-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -left-10 -bottom-10 h-40 w-40 bg-purple-500/10 rounded-full blur-3xl"></div>

                <div class="relative z-10 flex items-center gap-3 mb-2">
                    <div class="p-2 bg-indigo-500/10 rounded-xl backdrop-blur-md border border-indigo-500/20">
                        <Presentation class="h-5 w-5 text-indigo-400" />
                    </div>
                    <CardTitle
                        class="text-xl md:text-2xl font-display font-bold tracking-tight bg-gradient-to-r from-white to-zinc-400 bg-clip-text text-transparent">
                        Presentation Guide</CardTitle>
                </div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <CardDescription class="text-zinc-400 text-xs leading-relaxed max-w-[220px]">
                        Step-by-step structure for high-impact defense slides.
                    </CardDescription>
                    <div class="flex flex-col items-end gap-1.5">
                        <template v-if="deckSlides.length">
                            <Button variant="outline" size="sm"
                                class="h-7 text-[10px] gap-1.5 border-white/5 bg-white/5 hover:bg-white/10 text-zinc-400"
                                @click="emit('generateDefenseDeck')" :disabled="isDeckGenerating">
                                <RotateCcw class="h-3 w-3" />
                                Regenerate
                            </Button>
                            <div class="flex items-center gap-1.5">
                                <Button variant="secondary" size="sm"
                                    class="h-7 text-[10px] gap-1.5 px-3 font-bold"
                                    @click="emit('downloadPptx')" :disabled="isDeckGenerating">
                                    <Download class="h-3 w-3" />
                                    {{ isDeckGenerating ? 'Generating...' : 'Download PPTX' }}
                                </Button>
                                <Button variant="ghost" size="icon" class="h-7 w-7 text-zinc-500 hover:text-white"
                                    @click="guideExpanded = true">
                                    <Maximize2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </template>
                        <Button v-else variant="secondary" size="sm" class="h-8 text-xs gap-1.5 font-bold"
                            @click="emit('generateDefenseDeck')" :disabled="isDeckGenerating">
                            <Sparkles class="h-3.5 w-3.5" />
                            {{ isDeckGenerating ? 'Generating...' : 'Generate Guide' }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-4 md:p-6 h-[500px] flex flex-col">
                <div v-if="deckStatus !== 'idle'" class="mb-3 text-[11px] text-zinc-400">
                    <span v-if="deckStatus === 'queued'">Deck queued.</span>
                    <span v-else-if="deckStatus === 'outlining'">Generating slides with GPT-4o...</span>
                    <span v-else-if="deckStatus === 'extracting'">Extracting chapter data...</span>
                    <span v-else-if="deckStatus === 'extracted'">Extraction complete. Preparing slides...</span>
                    <span v-else-if="deckStatus === 'generating'">Generating slides with GPT-4o...</span>
                    <span v-else-if="deckStatus === 'outlined'">Slides ready for review.</span>
                    <span v-else-if="deckStatus === 'rendering'">Rendering PPTX...</span>
                    <span v-else-if="deckStatus === 'ready'">PPTX ready for download.</span>
                    <span v-else-if="deckStatus === 'failed'">Failed to generate deck.</span>
                </div>
                <div v-if="deckStatus === 'failed' && deckError" class="mb-3 text-[11px] text-rose-400">
                    {{ deckError }}
                </div>
                <DeckViewer :project="project" :slides="deckSlides" v-model:active-index="activeDeckIndex"
                    :is-saving="isDeckSaving" compact :show-pptx="!!deckId" :pptx-url="deckDownloadUrl"
                    :pptx-busy="isDeckGenerating" @update:slides="emit('update:deckSlides', $event)"
                    @toggle-expand="guideExpanded = true" @download-pptx="emit('downloadPptx')"
                    @export-pptx="emit('downloadPptx')" />
            </CardContent>
        </Card>

        <Dialog v-model:open="guideExpanded">
            <DialogContent
                class="max-w-[98vw] w-full lg:max-w-[98vw] h-[95vh] flex flex-col p-0 bg-zinc-100 dark:bg-zinc-950 border-white/10 shadow-2xl overflow-hidden rounded-3xl">
                <div v-if="isProduction" class="flex-1 flex items-center justify-center">
                    <div class="max-w-2xl text-center px-6">
                        <div
                            class="mx-auto mb-4 inline-flex items-center rounded-full border border-zinc-200/70 bg-white px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:border-white/10 dark:bg-white/5 dark:text-white/70">
                            Coming Soon
                        </div>
                        <h3 class="text-2xl md:text-3xl font-display font-bold text-zinc-900 dark:text-white">
                            Defense Slide Deck Editor
                        </h3>
                        <p class="mt-3 text-sm text-zinc-600 dark:text-white/70">
                            We are polishing the slide deck editor before release. Once ready, you will be able to edit
                            layouts, drag-and-drop elements, theme slides, and export a presentation-ready deck in
                            minutes.
                        </p>
                    </div>
                </div>
                <WysiwygSlideEditor v-else :slides="wysiwygSlides" :active-index="activeDeckIndex"
                    :is-saving="isDeckSaving" :project-title="project.title"
                    @update:slides="emit('update:wysiwygSlides', $event)"
                    @update:active-index="activeDeckIndex = $event" @export="emit('downloadPptx')" />
            </DialogContent>
        </Dialog>

        <Card class="border-border/50 shadow-sm rounded-3xl overflow-hidden">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <History class="h-4 w-4 text-amber-500" />
                    Session History
                </CardTitle>
                <CardDescription>Your past defense simulations for this project.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-if="isHistoryLoading" class="text-xs text-muted-foreground">
                    Loading sessions...
                </div>
                <div v-else-if="!sessionHistory.length" class="text-xs text-muted-foreground">
                    No sessions yet. Start a simulation to create your first one.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="sessionItem in sessionHistory" :key="sessionItem.id"
                        class="flex items-center justify-between gap-3 rounded-2xl border border-border/50 p-3">
                        <div class="space-y-1">
                            <div class="text-xs font-semibold text-foreground">
                                {{ formatSessionDate(sessionItem.started_at || sessionItem.created_at) }}
                            </div>
                            <div class="text-[11px] text-muted-foreground">
                                {{ sessionItem.status }} • {{ sessionItem.questions_asked }} Qs
                            </div>
                        </div>
                        <Button size="sm" variant="outline" @click="emit('openSession', sessionItem.id)">
                            {{ sessionItem.status === 'completed' ? 'Review' : 'Resume' }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
