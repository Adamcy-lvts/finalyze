<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Icon from '@/components/Icon.vue';

interface Props {
    open: boolean;
    rating: number | null;
    comment: string;
    isSubmitting?: boolean;
    commentError?: string;
    canSubmit?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isSubmitting: false,
    commentError: '',
    canSubmit: false,
});

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:rating', value: number | null): void;
    (event: 'update:comment', value: string): void;
    (event: 'submit'): void;
    (event: 'dismiss'): void;
}>();

const shouldShowComment = computed(() => props.rating !== null);
const requiresComment = computed(() => (props.rating ?? 0) < 3 && props.rating !== null);

const setRating = (value: number) => {
    emit('update:rating', value);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="space-y-2">
                <DialogTitle>How is your experience so far?</DialogTitle>
                <DialogDescription>
                    Help us improve by rating your experience with Finalyze.
                </DialogDescription>
            </DialogHeader>

            <div class="flex justify-center gap-2 py-2">
                <button
                    v-for="value in 5"
                    :key="value"
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border/60 transition hover:bg-muted"
                    :class="rating && value <= rating ? 'bg-amber-50 text-amber-600 border-amber-200' : 'text-muted-foreground'"
                    @click="setRating(value)"
                    :aria-label="`Rate ${value} stars`"
                >
                    <Icon name="star" class="h-5 w-5" :stroke-width="1.5" />
                </button>
            </div>

            <div v-if="shouldShowComment" class="space-y-2">
                <label class="text-sm font-medium">
                    {{ requiresComment ? 'What went wrong? (required)' : 'Anything else you want to share?' }}
                </label>
                <Textarea
                    :model-value="comment"
                    rows="4"
                    placeholder="Share your thoughts..."
                    @update:model-value="emit('update:comment', $event)"
                />
                <p v-if="commentError" class="text-sm text-destructive">{{ commentError }}</p>
            </div>

            <DialogFooter class="mt-4 flex flex-col gap-2 sm:flex-row">
                <Button type="button" variant="ghost" class="sm:flex-1" :disabled="isSubmitting" @click="emit('dismiss')">
                    Remind me in 3 days
                </Button>
                <Button type="button" class="sm:flex-1" :disabled="isSubmitting || !canSubmit" @click="emit('submit')">
                    Submit feedback
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
