<script setup lang="ts">
import { computed } from 'vue'
import { ref } from 'vue'
import { ChevronDown, Lightbulb, Bookmark, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import SafeHtmlText from '@/components/SafeHtmlText.vue'
import { hasHtmlContent } from '@/utils/html'
import type { UserChapterSuggestion, ContentAnalysis } from '@/types'

const props = defineProps<{
  suggestion: UserChapterSuggestion
  analysis?: ContentAnalysis | null
}>()

const emit = defineEmits<{
  save: []
  clear: []
  apply: []
}>()

const getSuggestionTypeBadge = (type: string) => {
  const badges: Record<string, { label: string; variant: string }> = {
    writing_guide: { label: 'Writing Guide', variant: 'default' },
    claims_without_evidence: { label: 'Evidence Needed', variant: 'destructive' },
    insufficient_citations: { label: 'Add Citations', variant: 'warning' },
    insufficient_tables: { label: 'Add Data', variant: 'secondary' },
    weak_arguments: { label: 'Strengthen Arguments', variant: 'warning' },
    insufficient_content: { label: 'Expand Content', variant: 'secondary' },
  }

  return badges[type] || { label: type, variant: 'default' }
}

const badgeInfo = getSuggestionTypeBadge(props.suggestion.suggestion_type)
const isOpen = ref(true)

const stripOuterCodeFence = (value: string) => {
  const trimmed = value.trim()
  const match = trimmed.match(/^```[a-zA-Z0-9_-]*\s*\n([\s\S]*?)\n?```$/)
  return match ? match[1].trim() : value
}

const convertPlainTextToHtml = (text: string): string => {
  const raw = stripOuterCodeFence(text).trim()
  if (!raw) return ''

  const blocks = raw.split(/\n\s*\n/).filter((block) => block.trim())
  return blocks
    .map((block) => {
      const trimmed = block.trim()

      const lines = trimmed
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)

      const isBulleted = lines.some((line) => /^[-*•]\s+/.test(line))
      const isNumbered = lines.some((line) => /^\d+\.\s+/.test(line))

      if (isBulleted || isNumbered) {
        const items = lines
          .map((line) => {
            if (isNumbered && /^\d+\.\s+/.test(line)) return `<li>${line.replace(/^\d+\.\s+/, '')}</li>`
            if (isBulleted && /^[-*•]\s+/.test(line)) return `<li>${line.replace(/^[-*•]\s+/, '')}</li>`
            return ''
          })
          .filter(Boolean)

        return isNumbered ? `<ol>${items.join('')}</ol>` : `<ul>${items.join('')}</ul>`
      }

      const paragraph = trimmed.replace(/\n/g, '<br>')
      return `<p>${paragraph}</p>`
    })
    .join('')
}

const formattedSuggestionContent = computed(() => {
  const content = props.suggestion?.suggestion_content || ''
  if (!content) return ''

  const unwrapped = stripOuterCodeFence(content)
  if (hasHtmlContent(unwrapped)) return unwrapped
  return convertPlainTextToHtml(unwrapped)
})
</script>

<template>
  <div class="bg-card/50 backdrop-blur-sm border rounded-xl shadow-sm transition-all hover:shadow-md">
    <Collapsible v-model:open="isOpen">
      <div class="flex items-start justify-between gap-4 p-4">
        <div class="flex-1 min-w-0">
          <CollapsibleTrigger as-child>
            <button
              type="button"
              class="w-full text-left flex items-center gap-2 rounded-lg hover:bg-muted/30 transition-colors pr-2 -mr-2"
            >
              <div class="p-1.5 rounded-md bg-primary/10 text-primary shrink-0">
                <Lightbulb class="w-4 h-4" />
              </div>
              <h3 class="font-semibold text-sm truncate">Smart Suggestion</h3>
              <Badge :variant="badgeInfo.variant as any" class="text-[10px] px-2 py-0.5 h-5 shrink-0">
                {{ badgeInfo.label }}
              </Badge>
              <ChevronDown
                class="w-4 h-4 ml-auto text-muted-foreground transition-transform duration-200 shrink-0"
                :class="isOpen ? 'rotate-180' : ''"
              />
            </button>
          </CollapsibleTrigger>
        </div>

        <div class="flex flex-col gap-1">
          <Button size="icon" variant="ghost" class="h-7 w-7" @click.stop="emit('clear')" title="Dismiss">
            <X class="w-4 h-4" />
          </Button>
          <Button size="icon" variant="ghost" class="h-7 w-7" @click.stop="emit('save')" title="Save for later">
            <Bookmark class="w-4 h-4" />
          </Button>
        </div>
      </div>

      <CollapsibleContent>
        <div class="px-4 pb-4 space-y-3">
          <SafeHtmlText
            as="div"
            :content="formattedSuggestionContent"
            class="prose prose-sm dark:prose-invert max-w-none text-sm text-muted-foreground leading-relaxed
              prose-p:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-pre:my-2 prose-code:text-xs"
          />

          <div v-if="analysis && analysis.detected_issues.length > 0" class="pt-2 border-t border-border/50">
            <p class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              Issues: {{ analysis.detected_issues.join(', ').replace(/_/g, ' ') }}
            </p>
          </div>

          <div class="pt-1 flex justify-end">
            <Button size="sm" variant="default" @click="emit('apply')" class="w-full sm:w-auto">
              Apply Suggestion
            </Button>
          </div>
        </div>
      </CollapsibleContent>
    </Collapsible>
  </div>
</template>
