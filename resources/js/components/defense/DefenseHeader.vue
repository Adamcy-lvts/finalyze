<script setup lang="ts">
import { ArrowLeft, Shield } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

defineProps<{
    isSimulating: boolean
    formattedReadinessScore: number | null
    overallReadinessScore: number | null
    lowBalanceMessage: string | null
}>()

const emit = defineEmits<{
    (e: 'back'): void
    (e: 'dismissLowBalance'): void
    (e: 'replayOnboarding'): void
}>()
</script>

<template>
    <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between mb-12">
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="sm" @click="emit('back')" class="h-8 w-8 rounded-full p-0"
                    :class="isSimulating ? 'text-zinc-400 hover:text-white' : ''">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <Badge variant="outline" class="gap-1 border-primary/20 bg-primary/5 text-primary">
                    <Shield class="h-3 w-3" />
                    Defense Readiness
                </Badge>
                <Button variant="ghost" size="sm"
                    class="text-[10px] uppercase tracking-widest text-muted-foreground"
                    :disabled="isSimulating" @click="emit('replayOnboarding')">
                    Replay tour
                </Button>
            </div>
            <h1 class="text-3xl font-bold tracking-tight md:text-6xl font-display"
                :class="isSimulating ? 'text-white' : 'text-foreground'">
                Prepare for Victory
            </h1>
            <p class="max-w-2xl text-base md:text-lg text-muted-foreground"
                :class="isSimulating ? 'text-zinc-400' : ''">
                Master your project defense with AI-predicted questions, structured presentation guides, and high-stakes
                simulation.
            </p>
            <div v-if="lowBalanceMessage"
                class="flex items-start justify-between gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100/90">
                <span>{{ lowBalanceMessage }}</span>
                <Button variant="ghost" size="sm" class="h-7 px-2 text-amber-100/90 hover:text-white"
                    @click="emit('dismissLowBalance')">
                    Dismiss
                </Button>
            </div>
        </div>

        <div v-show="!isSimulating"
            class="flex flex-row md:flex-col items-center gap-4 md:gap-3 p-4 md:p-6 rounded-3xl border border-primary/10 bg-primary/5 backdrop-blur-sm w-full md:w-auto">
            <div class="relative flex items-center justify-center shrink-0">
                <svg class="h-16 w-16 md:h-20 md:w-20 transform -rotate-90">
                    <circle class="text-muted/20 md:hidden" stroke-width="5" stroke="currentColor" fill="transparent"
                        r="28" cx="32" cy="32" />
                    <circle class="text-muted/20 hidden md:block" stroke-width="6" stroke="currentColor"
                        fill="transparent" r="34" cx="40" cy="40" />

                    <circle class="text-primary transition-all duration-1000 ease-out md:hidden" stroke-width="5"
                        :stroke-dasharray="2 * Math.PI * 28"
                        :stroke-dashoffset="2 * Math.PI * 28 * (1 - (formattedReadinessScore ?? 0) / 100)"
                        stroke-linecap="round" stroke="currentColor" fill="transparent" r="28" cx="32" cy="32" />
                    <circle class="text-primary transition-all duration-1000 ease-out hidden md:block" stroke-width="6"
                        :stroke-dasharray="2 * Math.PI * 34"
                        :stroke-dashoffset="2 * Math.PI * 34 * (1 - (formattedReadinessScore ?? 0) / 100)"
                        stroke-linecap="round" stroke="currentColor" fill="transparent" r="34" cx="40" cy="40" />
                </svg>
                <span class="absolute text-base md:text-xl font-bold">{{
                    formattedReadinessScore === null ? '—' : `${formattedReadinessScore}%`
                }}</span>
            </div>
            <span
                class="text-[10px] md:text-xs font-semibold uppercase tracking-wider text-muted-foreground text-center">
                {{ formattedReadinessScore === null ? 'Not started' : 'Readiness Score' }}
            </span>
            <span v-if="overallReadinessScore !== null"
                class="text-[10px] md:text-xs text-muted-foreground text-center">
                Overall {{ overallReadinessScore }}%
            </span>
        </div>
    </div>
</template>
