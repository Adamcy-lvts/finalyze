<script setup lang="ts">
import { computed } from 'vue'
import { ArrowUpDown, Brain, Sparkles, Target, History } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue'
import ExecutiveBriefingDeck from '@/components/defense/ExecutiveBriefingDeck.vue'
import PredictedQuestionsDeck from '@/components/defense/PredictedQuestionsDeck.vue'

interface PredictedQuestion {
    id?: number
    question: string
    suggested_answer: string
    category?: string
}

const props = defineProps<{
    isDeckSwapped: boolean
    predictedQuestions: PredictedQuestion[]
    isGeneratingQuestions: boolean
    briefingSlides: { title: string; content: string }[]
    executiveBriefing: string | null
    isBriefingLoading: boolean
    openingAnalysis: string | null
    isOpeningGenerating: boolean
    isOpeningAnalyzing: boolean
    modelValue: string
    highlightPrep: boolean
    highlightOpening: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'generatePredictedQuestions'): void
    (e: 'refreshExecutiveBriefing'): void
    (e: 'toggleDeckSwap'): void
    (e: 'generateOpeningStatement'): void
    (e: 'analyzeOpeningStatement'): void
}>()

const openingStatement = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})
</script>

<template>
    <div class="lg:col-span-8 transition-all duration-500 relative"
        :class="highlightPrep ? 'ring-2 ring-primary/40 ring-offset-2 ring-offset-background rounded-[2.5rem]' : ''">
        <div v-if="highlightOpening"
            class="absolute inset-0 bg-black/20 dark:bg-black/40 rounded-[2.5rem] pointer-events-none z-10">
        </div>
        <div class="space-y-8">
            <template v-if="!isDeckSwapped">
                <Card
                    class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <CardHeader class="pb-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                        <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                            <Sparkles class="h-5 w-5 text-indigo-500" />
                            Executive Briefing
                        </CardTitle>
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="sm" class="text-xs gap-1.5 h-8 w-fit"
                                @click="emit('refreshExecutiveBriefing')" :disabled="isBriefingLoading">
                                <History class="h-3.5 w-3.5" />
                                {{ isBriefingLoading ? 'Loading...' : 'Refresh AI' }}
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-zinc-500 hover:text-indigo-400"
                                @click="emit('toggleDeckSwap')" title="Swap position">
                                <ArrowUpDown class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <CardDescription class="text-indigo-600/70 dark:text-indigo-400/70 text-sm">
                        Your project's core value proposition, synthesized for your defense.
                    </CardDescription>
                </CardHeader>
                <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[400px]">
                    <div v-if="briefingSlides.length > 0" class="h-full">
                        <ExecutiveBriefingDeck :slides="briefingSlides" :is-loading="isBriefingLoading"
                            @refresh="emit('refreshExecutiveBriefing')" />
                    </div>
                    <div v-else-if="executiveBriefing" class="relative z-10 space-y-4">
                        <div
                            class="absolute -left-6 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-500/80 via-purple-500/80 to-transparent rounded-full opacity-60">
                        </div>
                        <RichTextViewer :content="executiveBriefing" :show-font-controls="false"
                            viewer-class="prose-sm md:prose-base dark:prose-invert leading-relaxed"
                            class="!bg-transparent" />
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center space-y-4">
                        <div class="h-12 w-12 rounded-full bg-indigo-500/10 flex items-center justify-center">
                            <Sparkles class="h-6 w-6 text-indigo-500/50" />
                        </div>
                        <p class="text-base text-muted-foreground italic max-w-xs">
                            No executive briefing yet. Click "Refresh AI" to generate a comprehensive summary of your
                            research.
                        </p>
                    </div>
                </CardContent>
                </Card>

                <div class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500 delay-75">
                    <Card class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50">
                        <CardHeader class="pb-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                                <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                    <Target class="h-5 w-5 text-rose-500" />
                                    Predicted Defense Questions
                                </CardTitle>
                                <div class="flex items-center gap-2">
                                    <Badge variant="secondary" class="font-mono text-[10px]">{{
                                        predictedQuestions.length }} TOP QUESTIONS</Badge>
                                    <Button variant="ghost" size="sm" class="text-xs h-8"
                                        @click="emit('generatePredictedQuestions')" :disabled="isGeneratingQuestions">
                                        <Sparkles class="h-3.5 w-3.5" />
                                        {{ isGeneratingQuestions ? 'Generating...' : 'Generate' }}
                                    </Button>
                                    <Button variant="ghost" size="icon"
                                        class="h-8 w-8 text-zinc-500 hover:text-rose-400"
                                        @click="emit('toggleDeckSwap')" title="Swap position">
                                        <ArrowUpDown class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <CardDescription class="text-rose-600/70 dark:text-rose-400/70 text-sm">
                                Identify and prepare for high-probability questions examiners might ask.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[450px]">
                            <PredictedQuestionsDeck :questions="predictedQuestions"
                                :is-loading="isGeneratingQuestions" />
                        </CardContent>
                    </Card>
                </div>
            </template>
            <template v-else>
                <div class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <Card class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50">
                        <CardHeader class="pb-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                                <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                    <Target class="h-5 w-5 text-rose-500" />
                                    Predicted Defense Questions
                                </CardTitle>
                                <div class="flex items-center gap-2">
                                    <Badge variant="secondary" class="font-mono text-[10px]">{{
                                        predictedQuestions.length }} TOP QUESTIONS</Badge>
                                    <Button variant="ghost" size="sm" class="text-xs h-8"
                                        @click="emit('generatePredictedQuestions')" :disabled="isGeneratingQuestions">
                                        <Sparkles class="h-3.5 w-3.5" />
                                        {{ isGeneratingQuestions ? 'Generating...' : 'Generate' }}
                                    </Button>
                                    <Button variant="ghost" size="icon"
                                        class="h-8 w-8 text-zinc-500 hover:text-rose-400"
                                        @click="emit('toggleDeckSwap')" title="Swap position">
                                        <ArrowUpDown class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <CardDescription class="text-rose-600/70 dark:text-rose-400/70 text-sm">
                                Identify and prepare for high-probability questions examiners might ask.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[450px]">
                            <PredictedQuestionsDeck :questions="predictedQuestions"
                                :is-loading="isGeneratingQuestions" />
                        </CardContent>
                    </Card>
                </div>

                <Card
                    class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50 animate-in fade-in slide-in-from-bottom-4 duration-500 delay-75">
                    <CardHeader class="pb-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                            <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                <Sparkles class="h-5 w-5 text-indigo-500" />
                                Executive Briefing
                            </CardTitle>
                            <div class="flex items-center gap-2">
                                <Button variant="ghost" size="sm" class="text-xs gap-1.5 h-8 w-fit"
                                    @click="emit('refreshExecutiveBriefing')" :disabled="isBriefingLoading">
                                    <History class="h-3.5 w-3.5" />
                                    {{ isBriefingLoading ? 'Loading...' : 'Refresh AI' }}
                                </Button>
                                <Button variant="ghost" size="icon"
                                    class="h-8 w-8 text-zinc-500 hover:text-indigo-400"
                                    @click="emit('toggleDeckSwap')" title="Swap position">
                                    <ArrowUpDown class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <CardDescription class="text-indigo-600/70 dark:text-indigo-400/70 text-sm">
                            Your project's core value proposition, synthesized for your defense.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[400px]">
                        <div v-if="briefingSlides.length > 0" class="h-full">
                            <ExecutiveBriefingDeck :slides="briefingSlides" :is-loading="isBriefingLoading"
                                @refresh="emit('refreshExecutiveBriefing')" />
                        </div>
                        <div v-else-if="executiveBriefing" class="relative z-10 space-y-4">
                            <div
                                class="absolute -left-6 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-500/80 via-purple-500/80 to-transparent rounded-full opacity-60">
                            </div>
                            <RichTextViewer :content="executiveBriefing" :show-font-controls="false"
                                viewer-class="prose-sm md:prose-base dark:prose-invert leading-relaxed"
                                class="!bg-transparent" />
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-center space-y-4">
                            <div class="h-12 w-12 rounded-full bg-indigo-500/10 flex items-center justify-center">
                                <Sparkles class="h-6 w-6 text-indigo-500/50" />
                            </div>
                            <p class="text-base text-muted-foreground italic max-w-xs">
                                No executive briefing yet. Click "Refresh AI" to generate a comprehensive summary of
                                your research.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <Card class="border-border/50 shadow-sm rounded-3xl overflow-hidden group" :class="highlightOpening
                ? 'relative z-20 ring-2 ring-primary/50 shadow-[0_0_0_6px_rgba(14,165,233,0.15)]'
                : highlightPrep
                    ? 'opacity-40 transition-opacity'
                    : ''">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    Opening Statement
                </CardTitle>
                <CardDescription>Your 60-second "hook" to impress the panel.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="relative">
                    <textarea v-model="openingStatement" rows="4"
                        class="w-full rounded-2xl border-border/50 bg-muted/30 p-4 text-sm focus:ring-primary/20 transition-all"
                        placeholder="Type your opening statement here..."></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Button variant="outline" class="w-full text-xs font-bold gap-2 rounded-xl"
                        @click="emit('generateOpeningStatement')" :disabled="isOpeningGenerating">
                        <Sparkles class="h-3.5 w-3.5" />
                        {{ isOpeningGenerating ? 'Generating...' : 'GENERATE OPENING' }}
                    </Button>
                    <Button variant="secondary" class="w-full text-xs font-bold gap-2 rounded-xl"
                        @click="emit('analyzeOpeningStatement')" :disabled="isOpeningAnalyzing">
                        <Brain class="h-3.5 w-3.5" />
                        {{ isOpeningAnalyzing ? 'Analyzing...' : 'ANALYZE PITCH FLOW' }}
                    </Button>
                </div>
                <div v-if="openingAnalysis"
                    class="text-sm text-muted-foreground border-t border-emerald-500/10 pt-4 mt-2">
                    <RichTextViewer :content="openingAnalysis" :show-font-controls="false"
                        class="!bg-transparent" viewer-class="prose-xs md:prose-sm" />
                </div>
            </CardContent>
            </Card>
        </div>
    </div>
</template>
