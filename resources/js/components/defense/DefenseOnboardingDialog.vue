<script setup lang="ts">
import { computed, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent } from '@/components/ui/dialog'

interface OnboardingStep {
    title: string
    description: string
}

const props = defineProps<{
    modelValue: boolean
    steps: OnboardingStep[]
    step: number
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'update:step', value: number): void
    (e: 'complete'): void
    (e: 'skip'): void
}>()

const stepIndex = computed({
    get: () => props.step,
    set: value => emit('update:step', value),
})
const isOpen = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})

const currentStep = computed(() => props.steps[stepIndex.value])
const isLastStep = computed(() => stepIndex.value >= props.steps.length - 1)

watch(() => props.modelValue, value => {
    if (value) {
        stepIndex.value = 0
    }
})

const goNext = () => {
    if (isLastStep.value) {
        emit('complete')
        emit('update:modelValue', false)
        return
    }
    stepIndex.value += 1
}

const goBack = () => {
    stepIndex.value = Math.max(0, stepIndex.value - 1)
}

const skip = () => {
    emit('skip')
    emit('update:modelValue', false)
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="max-w-xl w-[95vw] rounded-3xl border border-border/60 bg-background/95 backdrop-blur-xl shadow-2xl">
            <div class="space-y-6">
                <div class="space-y-2">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.3em] text-muted-foreground">
                        Step {{ stepIndex + 1 }} of {{ steps.length }}
                    </div>
                    <h3 class="text-xl md:text-2xl font-display font-bold text-foreground">
                        {{ currentStep?.title }}
                    </h3>
                    <p class="text-sm md:text-base text-muted-foreground leading-relaxed">
                        {{ currentStep?.description }}
                    </p>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <Button variant="ghost" size="sm" class="text-xs" @click="skip">
                        Skip
                    </Button>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" class="text-xs" :disabled="stepIndex === 0"
                            @click="goBack">
                            Back
                        </Button>
                        <Button size="sm" class="text-xs font-semibold" @click="goNext">
                            {{ isLastStep ? 'Start' : 'Next' }}
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
