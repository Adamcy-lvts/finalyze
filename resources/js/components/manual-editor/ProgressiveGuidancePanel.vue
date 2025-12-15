<script setup lang="ts">
import { computed, ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Separator } from '@/components/ui/separator'
import {
    Target,
    ChevronDown,
    Lightbulb,
    CheckCircle2,
    Circle,
    Loader2,
} from 'lucide-vue-next'
import type { ProgressiveGuidanceData } from '@/composables/useProgressiveGuidance'

interface Props {
    guidance: ProgressiveGuidanceData | null
    isLoading: boolean
    hasContent?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
    toggleStep: [stepId: string]
    guidanceAction: [stepId: string]
}>()

const isOpen = ref(true)

const stageColor = computed(() => {
    const stage = props.guidance?.stage || 'planning'
    const colors: Record<string, string> = {
        planning: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        introduction: 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
        body_development: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        body_advanced: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
        refinement: 'bg-green-500/10 text-green-600 dark:text-green-400',
    }
    return colors[stage] || colors.planning
})

const completedStepsCount = computed(() => {
    if (!props.guidance) {
        return 0
    }
    return props.guidance.next_steps.filter(step => step.completed).length
})

const totalStepsCount = computed(() => {
    return props.guidance?.next_steps.length || 0
})
</script>

<template>
    <div class="space-y-2">
        <Collapsible v-model:open="isOpen" class="space-y-2">
            <CollapsibleTrigger
                class="flex w-full items-center justify-between rounded-xl p-4 hover:bg-accent/50 transition-colors duration-200 group"
            >
                <div class="flex items-center gap-3">
                    <div :class="['p-2 rounded-lg', stageColor]">
                        <Target class="h-4 w-4" />
                    </div>
                    <div class="flex flex-col items-start gap-1">
                        <span class="text-sm font-semibold">Writing Progress</span>
                        <span v-if="guidance" class="text-[10px] text-muted-foreground">
                            {{ guidance.stage_label }}
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
                <!-- Loading State -->
                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>

                <!-- Guidance Content -->
                <template v-else-if="guidance">
                    <!-- Progress Bar -->
                    <div class="space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-foreground">Overall Progress</span>
                            <span class="text-muted-foreground">
                                {{ guidance.completion_percentage }}%
                            </span>
                        </div>
                        <Progress
                            :model-value="guidance.completion_percentage"
                            class="h-2"
                        />
                    </div>

                    <Separator class="bg-border/50" />

                    <!-- Next Steps -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-primary" />
                                Next Steps
                            </h4>
                            <Badge variant="secondary" class="text-[10px] px-2 py-0.5">
                                {{ completedStepsCount }} / {{ totalStepsCount }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="step in guidance.next_steps"
                                :key="step.id"
                                class="flex items-start gap-3 p-3 rounded-lg hover:bg-accent/30 transition-colors duration-200 cursor-pointer group"
                                @click="emit('toggleStep', step.id)"
                            >
                                <Checkbox
                                    :checked="step.completed"
                                    @update:checked="emit('toggleStep', step.id)"
                                    class="mt-0.5"
                                />
                                <span
                                    :class="[
                                        'text-xs leading-relaxed flex-1',
                                        step.completed
                                            ? 'line-through text-muted-foreground'
                                            : 'text-foreground',
                                    ]"
                                >
                                    {{ step.text }}
                                </span>
                                <Button
                                    v-if="step.action && step.action !== 'none'"
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    class="h-7 px-2 text-[11px] text-primary hover:bg-primary/10"
                                    @click.stop="emit('guidanceAction', step.id)"
                                >
                                    {{ step.action === 'open_citation_helper' ? 'Citations' : 'Insert' }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Contextual Tip -->
                    <div
                        v-if="guidance.contextual_tip"
                        class="p-3 rounded-lg bg-blue-500/10 border border-blue-500/20"
                    >
                        <div class="flex items-start gap-2">
                            <Lightbulb class="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                            <div class="flex-1">
                                <p class="text-xs leading-relaxed text-foreground">
                                    {{ guidance.contextual_tip }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator class="bg-border/50" />

                    <!-- Writing Milestones -->
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold flex items-center gap-2">
                            <Target class="h-4 w-4 text-primary" />
                            Milestones
                        </h4>

                        <div class="space-y-2">
                            <div
                                v-for="milestone in guidance.writing_milestones"
                                :key="milestone.id"
                                class="flex items-center gap-3 p-2 rounded-lg"
                            >
                                <component
                                    :is="milestone.completed ? CheckCircle2 : Circle"
                                    :class="[
                                        'h-4 w-4',
                                        milestone.completed
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-muted-foreground',
                                    ]"
                                />
                                <span
                                    :class="[
                                        'text-xs',
                                        milestone.completed
                                            ? 'text-foreground font-medium'
                                            : 'text-muted-foreground',
                                    ]"
                                >
                                    {{ milestone.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div v-else class="text-center py-8">
                    <Target class="h-12 w-12 mx-auto text-muted-foreground/50 mb-3" />
                    <p class="text-sm text-muted-foreground">
                        <template v-if="hasContent">
                            Analyzing your chapter to generate personalized guidance…
                        </template>
                        <template v-else>
                            Start writing to get personalized guidance
                        </template>
                    </p>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
