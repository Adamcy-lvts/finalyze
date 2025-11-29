<script setup lang="ts">
import { ref } from 'vue'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
    Sparkles,
    AlignLeft,
    Quote,
    Type,
    ChevronDown,
    Wand2,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { router } from '@inertiajs/vue3'

interface Props {
    projectSlug: string
    chapterNumber: number
    selectedText: string
    isProcessing?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
    textImproved: [text: string]
    textExpanded: [text: string]
    citationsSuggested: [suggestions: string]
    textRephrased: [alternatives: string]
}>()

const isOpen = ref(true)

const actionLoadingStates = ref({
    improve: false,
    expand: false,
    cite: false,
    rephrase: false,
})

/**
 * Handle quick action button click
 */
const handleQuickAction = async (action: 'improve' | 'expand' | 'cite' | 'rephrase') => {
    if (!props.selectedText && (action === 'expand' || action === 'rephrase')) {
        toast.error('Please select some text first')
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
            case 'cite':
                routeName = 'projects.manual-editor.suggest-citations'
                eventName = 'citationsSuggested'
                break
            case 'rephrase':
                routeName = 'projects.manual-editor.rephrase-text'
                eventName = 'textRephrased'
                break
        }

        const response = await fetch(
            route(routeName, {
                project: props.projectSlug,
                chapter: props.chapterNumber,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    text: props.selectedText,
                }),
            }
        )

        if (!response.ok) {
            throw new Error('Failed to process request')
        }

        const data = await response.json()

        // Emit the result
        if (action === 'improve' && data.improvedText) {
            emit('textImproved', data.improvedText)
            toast.success('Text improved successfully!')
        } else if (action === 'expand' && data.expandedText) {
            emit('textExpanded', data.expandedText)
            toast.success('Text expanded successfully!')
        } else if (action === 'cite' && data.suggestions) {
            emit('citationsSuggested', data.suggestions)
            toast.success('Citation suggestions generated!')
        } else if (action === 'rephrase' && data.alternatives) {
            emit('textRephrased', data.alternatives)
            toast.success('Alternative phrasings generated!')
        }
    } catch (error) {
        console.error(`Failed to ${action} text:`, error)
        toast.error(`Failed to ${action} text. Please try again.`)
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
</template>
