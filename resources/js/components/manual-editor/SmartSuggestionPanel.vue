<script setup lang="ts">
import { Lightbulb, Bookmark, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
</script>

<template>
  <div class="bg-card/50 backdrop-blur-sm border rounded-xl p-4 shadow-sm transition-all hover:shadow-md">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1 space-y-3">
        <div class="flex items-center gap-2">
          <div class="p-1.5 rounded-md bg-primary/10 text-primary">
            <Lightbulb class="w-4 h-4" />
          </div>
          <h3 class="font-semibold text-sm">Smart Suggestion</h3>
          <Badge :variant="badgeInfo.variant as any" class="text-[10px] px-2 py-0.5 h-5">
            {{ badgeInfo.label }}
          </Badge>
        </div>

        <div
          class="prose prose-sm dark:prose-invert max-w-none text-sm text-muted-foreground leading-relaxed"
          v-html="suggestion.suggestion_content"
        />

        <div v-if="analysis && analysis.detected_issues.length > 0" class="pt-2 border-t border-border/50">
          <p class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            Issues: {{ analysis.detected_issues.join(', ').replace(/_/g, ' ') }}
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <Button size="icon" variant="ghost" class="h-7 w-7" @click="emit('clear')" title="Dismiss">
          <X class="w-4 h-4" />
        </Button>
        <Button size="icon" variant="ghost" class="h-7 w-7" @click="emit('save')" title="Save for later">
          <Bookmark class="w-4 h-4" />
        </Button>
      </div>
    </div>
    
    <div class="mt-4 flex justify-end">
      <Button size="sm" variant="default" @click="emit('apply')" class="w-full sm:w-auto">
        Apply Suggestion
      </Button>
    </div>
  </div>
</template>
