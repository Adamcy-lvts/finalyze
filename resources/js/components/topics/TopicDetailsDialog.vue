<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export interface TopicDetails {
    id: number;
    title: string;
    description: string;
    difficulty?: string;
    timeline?: string;
    resource_level?: string;
    feasibility_score?: number;
    keywords?: string[];
    research_type?: string;
    field_of_study?: string;
    faculty?: string;
    course?: string;
    academic_level?: string;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        topic: TopicDetails | null;
        titleLabel?: string;
        closeLabel?: string;
    }>(),
    {
        titleLabel: 'Topic Details',
        closeLabel: 'Close',
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const metaBadges = computed(() => {
    if (!props.topic) return [];

    const badges: Array<{ label: string; variant?: 'secondary' | 'outline' }> = [];
    if (props.topic.difficulty) badges.push({ label: props.topic.difficulty, variant: 'outline' });
    if (props.topic.timeline) badges.push({ label: props.topic.timeline, variant: 'outline' });
    if (props.topic.resource_level) badges.push({ label: props.topic.resource_level, variant: 'outline' });
    if (props.topic.research_type) badges.push({ label: props.topic.research_type, variant: 'secondary' });
    if (typeof props.topic.feasibility_score === 'number') badges.push({ label: `${props.topic.feasibility_score}% match`, variant: 'secondary' });
    return badges;
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-3xl">
            <DialogHeader class="space-y-2">
                <DialogTitle class="text-sm text-muted-foreground">{{ titleLabel }}</DialogTitle>
                <SafeHtmlText v-if="topic" :content="topic.title" as="h2" class="text-lg md:text-2xl font-bold leading-snug" />
            </DialogHeader>

            <div v-if="topic" class="space-y-5">
                <div class="flex flex-wrap gap-2">
                    <Badge v-for="(b, idx) in metaBadges" :key="idx" :variant="b.variant ?? 'outline'">
                        {{ b.label }}
                    </Badge>
                    <Badge v-if="topic.faculty || topic.field_of_study" variant="outline">
                        {{ topic.faculty || topic.field_of_study }}
                    </Badge>
                    <Badge v-if="topic.course" variant="outline">{{ topic.course }}</Badge>
                    <Badge v-if="topic.academic_level" variant="outline">{{ topic.academic_level }}</Badge>
                </div>

                <div class="rounded-lg border border-border/50 bg-muted/20 p-4">
                    <SafeHtmlText :content="topic.description" as="div" class="text-sm leading-relaxed text-foreground" />
                </div>

                <div v-if="topic.keywords?.length" class="space-y-2">
                    <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Keywords</div>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="(kw, idx) in topic.keywords" :key="idx" variant="secondary" class="text-xs">
                            {{ kw }}
                        </Badge>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <slot name="footer" :topic="topic" />
                <Button type="button" variant="outline" @click="emit('update:open', false)">{{ closeLabel }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

