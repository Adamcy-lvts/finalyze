<script setup lang="ts">
import { ref } from 'vue'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { route } from 'ziggy-js'
import { countWords } from '@/utils/wordCount'
import {
    Sparkles,
    AlignLeft,
    Quote,
    Type,
    ChevronDown,
    Wand2,
    MessageSquarePlus,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import CustomPromptDialog from '@/components/chapter-editor/CustomPromptDialog.vue'

interface Props {
    projectSlug: string
    chapterNumber: number
    selectedText: string
    chapterContent: string
    isProcessing?: boolean
    ensureBalance?: (requiredWords: number, action: string) => boolean
    onUsage?: (wordsUsed: number, description: string) => void | Promise<void>
}

const props = defineProps<Props>()

const emit = defineEmits<{
    textImproved: [text: string]
    textExpanded: [text: string]
    citationsSuggested: [suggestions: string]
    textRephrased: [alternatives: string]
    customPromptExecuted: [prompt: string]
    openCitationHelper: []
}>()

const isOpen = ref(true)

const actionLoadingStates = ref({
    improve: false,
    expand: false,
    cite: false,
    rephrase: false,
    custom: false,
})

// Custom prompt dialog state - DISABLED
// const showCustomPromptDialog = ref(false)

// Handle custom prompt execution - DISABLED
// const handleCustomPrompt = (prompt: string) => {
//     actionLoadingStates.value.custom = true
//     emit('customPromptExecuted', prompt)
//     toast('Executing custom prompt...')
//     setTimeout(() => {
//         actionLoadingStates.value.custom = false
//     }, 1000)
// }

const getBodyTextForAction = (action: 'improve' | 'expand' | 'cite' | 'rephrase') => {
    const selection = props.selectedText?.trim() || ''
    const content = props.chapterContent?.trim() || ''

    if (action === 'expand' || action === 'rephrase') return selection
    return content || selection
}

const readErrorMessage = async (response: Response): Promise<string> => {
    try {
        const data = await response.json()
        return data?.message || data?.error || `HTTP ${response.status}`
    } catch {
        return `HTTP ${response.status}`
    }
}

/**
 * Handle quick action button click
 */
const handleQuickAction = async (action: 'improve' | 'expand' | 'cite' | 'rephrase') => {
    if (props.ensureBalance && !props.ensureBalance(300, 'use quick actions')) {
        return
    }

    const bodyText = getBodyTextForAction(action)

    if (!bodyText) {
        toast.error(action === 'expand' || action === 'rephrase' ? 'Please select some text first' : 'Please add some content first')
        return
    }

    if (!props.selectedText && (action === 'expand' || action === 'rephrase')) {
        toast.error('Please select some text first')
        return
    }

    // For cite action, just open the CitationHelper panel (same as ChapterEditor)
    if (action === 'cite') {
        emit('openCitationHelper')
        toast('Opening citation helper...')
        return
    }

    actionLoadingStates.value[action] = true

    try {
        let routeName = ''
        let eventName: 'textImproved' | 'textExpanded' | 'citationsSuggested' | 'textRephrased'

        switch (action) {
            case 'improve':
                routeName = 'projects.manual-editor.improve-text'
                eventName = 'textImproved'
                break
            case 'expand':
                routeName = 'projects.manual-editor.expand-text'
                eventName = 'textExpanded'
                break
            case 'rephrase':
                routeName = 'projects.manual-editor.rephrase-text'
                eventName = 'textRephrased'
                break
        }

        const response = await fetch(route(routeName, { project: props.projectSlug, chapter: props.chapterNumber }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ text: bodyText }),
        })

        if (!response.ok) {
            const message = await readErrorMessage(response)
            throw new Error(message)
        }

        const data = await response.json()

        // Emit the result
        if (action === 'improve' && data.improvedText) {
            emit('textImproved', data.improvedText)
            await props.onUsage?.(countWords(data.improvedText), 'Manual editor: Improve')
            toast.success('Text improved successfully!')
        } else if (action === 'expand' && data.expandedText) {
            emit('textExpanded', data.expandedText)
            await props.onUsage?.(countWords(data.expandedText), 'Manual editor: Expand')
            toast.success('Text expanded successfully!')
        } else if (action === 'rephrase' && data.alternatives) {
            emit('textRephrased', data.alternatives)
            await props.onUsage?.(countWords(data.alternatives), 'Manual editor: Rephrase')
            toast.success('Alternative phrasings generated!')
        }
    } catch (error) {
        console.error(`Failed to ${action} text:`, error)
        const message = error instanceof Error ? error.message : ''
        toast.error(`Failed to ${action} text. Please try again.`, message ? { description: message } : undefined)
    } finally {
        actionLoadingStates.value[action] = false
    }
}
</script>

<template>
    <div class="space-y-2">
        <Collapsible v-model:open="isOpen" class="space-y-2">
            <CollapsibleTrigger
                class="flex w-full items-center justify-between rounded-xl p-4 hover:bg-accent/50 transition-colors duration-200 group"
            >
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                        <Wand2 class="h-4 w-4" />
                    </div>
                    <div class="flex flex-col items-start gap-1">
                        <span class="text-sm font-semibold">Quick Actions</span>
                        <span class="text-[10px] text-muted-foreground">
                            AI-powered writing tools
                        </span>
                    </div>
                </div>
                <ChevronDown
                    :class="[
                        'h-4 w-4 text-muted-foreground transition-transform duration-200',
                        isOpen ? 'rotate-180' : '',
                    ]"
                />
            </CollapsibleTrigger>

            <CollapsibleContent class="space-y-4 px-4 pb-4">
                <!-- Action Grid -->
                <div class="grid grid-cols-2 gap-2">
                    <!-- Improve Button -->
                    <Button
                        @click="handleQuickAction('improve')"
                        :disabled="isProcessing || actionLoadingStates.improve"
                        variant="outline"
                        class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300"
                    >
                        <div class="p-1.5 rounded-full bg-blue-500/10 text-blue-500">
                            <Sparkles class="h-4 w-4" />
                        </div>
                        <span class="text-xs font-medium">
                            {{ actionLoadingStates.improve ? 'Improving...' : 'Improve' }}
                        </span>
                    </Button>

                    <!-- Expand Button -->
                    <Button
                        @click="handleQuickAction('expand')"
                        :disabled="!selectedText || isProcessing || actionLoadingStates.expand"
                        variant="outline"
                        class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300"
                    >
                        <div class="p-1.5 rounded-full bg-purple-500/10 text-purple-500">
                            <AlignLeft class="h-4 w-4" />
                        </div>
                        <span class="text-xs font-medium">
                            {{ actionLoadingStates.expand ? 'Expanding...' : 'Expand' }}
                        </span>
                    </Button>

                    <!-- Cite Button -->
                    <Button
                        @click="handleQuickAction('cite')"
                        :disabled="isProcessing || actionLoadingStates.cite"
                        variant="outline"
                        class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300"
                    >
                        <div class="p-1.5 rounded-full bg-orange-500/10 text-orange-500">
                            <Quote class="h-4 w-4" />
                        </div>
                        <span class="text-xs font-medium">
                            {{ actionLoadingStates.cite ? 'Citing...' : 'Cite' }}
                        </span>
                    </Button>

                    <!-- Rephrase Button -->
                    <Button
                        @click="handleQuickAction('rephrase')"
                        :disabled="!selectedText || isProcessing || actionLoadingStates.rephrase"
                        variant="outline"
                        class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300"
                    >
                        <div class="p-1.5 rounded-full bg-emerald-500/10 text-emerald-500">
                            <Type class="h-4 w-4" />
                        </div>
                        <span class="text-xs font-medium">
                            {{ actionLoadingStates.rephrase ? 'Rephrasing...' : 'Rephrase' }}
                        </span>
                    </Button>

                    <!-- Custom Prompt Button - DISABLED
                    <Button
                        @click="showCustomPromptDialog = true"
                        :disabled="isProcessing || actionLoadingStates.custom"
                        variant="outline"
                        class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300 col-span-2"
                    >
                        <div class="p-1.5 rounded-full bg-gradient-to-r from-purple-500/10 to-indigo-500/10 text-purple-500">
                            <MessageSquarePlus class="h-4 w-4" />
                        </div>
                        <span class="text-xs font-medium">
                            {{ actionLoadingStates.custom ? 'Executing...' : 'Custom Prompt' }}
                        </span>
                    </Button>
                    -->
                </div>

                <!-- Help Text -->
                <div class="p-3 rounded-lg bg-muted/50 border border-border/50">
                    <p class="text-[10px] text-muted-foreground leading-relaxed">
                        <strong>Tip:</strong> Select text before using Expand or Rephrase.
                        Improve and Cite work on your entire content.
                    </p>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>

    <!-- Custom Prompt Dialog - DISABLED
    <CustomPromptDialog 
        v-model:open="showCustomPromptDialog"
        :selected-text="selectedText"
        :chapter-content="chapterContent"
        @execute-prompt="handleCustomPrompt"
    />
    -->
</template>
